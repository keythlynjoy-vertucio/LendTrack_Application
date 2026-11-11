<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Borrower, Loan, Payment};
use App\Services\LoanMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManagementApiController extends Controller
{
    public function dashboard(Request $request, LoanMetricsService $metrics)
    {
        abort_unless($request->user()?->hasPermission('view_dashboard'), 403);
        return response()->json([
            'as_of' => today()->toDateString(),
            'borrowers' => Borrower::count(),
            'portfolio' => $metrics->getPortfolioTotals(),
            'monthly_collections' => $metrics->getRecentMonthlyCollections(6),
        ]);
    }

    public function activity(Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = trim($data['search'] ?? '');
        return response()->json(ActivityLog::with('user:id,name')
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%");
            }))->latest()->orderByDesc('id')->paginate(20)->withQueryString());
    }

    public function discrepancies(Request $request, LoanMetricsService $metrics)
    {
        $this->admin($request);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $asOf = Carbon::parse($data['as_of'] ?? today()->toDateString())->endOfDay();
        $search = trim($data['search'] ?? '');
        $page = Loan::with(['borrower', 'payments'])
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('reference_no', 'like', "%{$search}%")
                    ->orWhereHas('borrower', fn ($b) => $b
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            }))->orderByDesc('id')->paginate(10)->withQueryString();
        $page->setCollection($page->getCollection()->map(function ($loan) use ($metrics, $asOf) {
            $schedule = $metrics->collectionSchedule($loan, $asOf);
            $last = count($schedule) ? $schedule[array_key_last($schedule)]['due_date'] : null;
            $paid = $metrics->getLoanPaymentTotals($loan)['regular'];
            $warnings = [];
            if (!$loan->first_due_date) $warnings[] = 'First due date is missing; schedule uses the start date.';
            if ($last && $loan->due_date && $last !== $loan->due_date->toDateString()) {
                $warnings[] = 'Stored final due date differs from the installment schedule.';
            }
            if (abs((float) $loan->total_paid - $paid) >= 0.01) $warnings[] = 'Stored total paid differs from payment records.';
            if (strtolower(trim($loan->status)) !== $metrics->calculateLoanStatus($loan)) {
                $warnings[] = 'Stored status differs from the current calculated status.';
            }
            return [
                'id' => $loan->id, 'reference_no' => $loan->reference_no,
                'borrower' => $loan->borrower?->full_name, 'status' => $loan->status,
                'stored_due_date' => $loan->due_date?->toDateString(),
                'schedule_due_date' => $last, 'warnings' => $warnings,
                'summary' => $metrics->buildDiscrepancySchedule($loan, $asOf)['summary'],
            ];
        }));
        return response()->json(array_merge($page->toArray(), ['as_of' => $asOf->toDateString()]));
    }

    public function records(Request $request, string $type)
    {
        $this->admin($request);
        $data = $request->validate([
            'state' => ['nullable', Rule::in(['current', 'deleted'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $model = $this->model($type);
        $query = $model::query();
        if (($data['state'] ?? 'deleted') === 'deleted') $query->onlyTrashed();
        $search = trim($data['search'] ?? '');
        if ($search !== '') {
            $query->where(function ($q) use ($type, $search) {
                $q->where('id', $search);
                if ($type === 'borrowers') {
                    $q->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                } else {
                    $q->orWhere('reference_no', 'like', "%{$search}%");
                }
            });
        }
        return response()->json($query->orderByDesc('id')->paginate(15)->withQueryString());
    }

    public function changeRecord(Request $request, string $type, int $id, LoanMetricsService $metrics)
    {
        $this->admin($request);
        $data = $request->validate([
            'action' => ['required', Rule::in(['delete', 'restore'])],
            'reason' => ['required', 'string', 'max:500', 'regex:/\S/'],
        ]);
        $model = $this->model($type);
        DB::transaction(function () use ($request, $type, $id, $data, $model, $metrics) {
            // Serialize payment changes using the same loan lock as mobile payment creation.
            $parent = null;
            if ($type === 'payments') {
                $payment = Payment::withTrashed()->findOrFail($id);
                $parent = Loan::withTrashed()->lockForUpdate()->findOrFail($payment->loan_id);
            }
            if ($type === 'loans' && $data['action'] === 'restore') {
                $loan = Loan::withTrashed()->findOrFail($id);
                Borrower::withTrashed()->whereKey($loan->borrower_id)->lockForUpdate()->firstOrFail();
            }
            $record = $model::withTrashed()->lockForUpdate()->findOrFail($id);
            $restore = $data['action'] === 'restore';
            abort_if($restore !== $record->trashed(), 409, 'Record state changed. Refresh and try again.');
            if ($type === 'borrowers' && !$restore) {
                abort_if($record->loans()->exists(), 422, 'Archive this borrower\'s loans first.');
            }
            if ($type === 'loans') {
                if ($restore) {
                    abort_unless(Borrower::whereKey($record->borrower_id)->exists(), 422, 'Restore the borrower first.');
                } else {
                    abort_if($record->payments()->exists(), 422, 'A loan with current payment records cannot be archived.');
                }
            }
            if ($type === 'payments' && $restore) {
                abort_if($parent->trashed() || !Borrower::whereKey($parent->borrower_id)->exists(), 422, 'Restore the loan and borrower first.');
                $paid = (float) $parent->payments()->sum('amount');
                abort_if(round($paid + (float) $record->amount, 2) > round((float) $parent->total_payable, 2), 422,
                    'Restoring this payment would exceed the regular loan balance. Review the records first.');
            }
            $restore ? $record->restore() : $record->delete();
            if ($parent) {
                $parent->unsetRelation('payments');
                $metrics->syncLoanTotals($parent);
            }
            ActivityLog::create([
                'user_id' => $request->user()->id, 'subject_type' => $model,
                'subject_id' => $id, 'action' => $restore ? 'restored' : 'deleted',
                'description' => ucfirst($data['action'])." {$type} #{$id}: ".trim($data['reason']),
                'changes' => ['reason' => trim($data['reason'])],
            ]);
        });
        return response()->json(['message' => 'Record updated successfully.']);
    }

    public function receipt(Request $request, Payment $payment)
    {
        abort_unless($request->user()?->hasPermission('view_payments'), 403);
        $payment->load(['loan.borrower', 'user:id,name']);
        return response()->json([
            'id' => $payment->id, 'loan_reference' => $payment->loan?->reference_no,
            'borrower' => $payment->loan?->borrower?->full_name,
            'received_by' => $payment->user?->name, 'date' => $payment->paid_at?->toDateString(),
            'regular_amount' => $payment->amount, 'penalty_paid' => $payment->penalty_paid,
            'total_received' => number_format((float) $payment->amount + (float) $payment->penalty_paid, 2, '.', ''),
            'method' => $payment->method, 'reference_no' => $payment->reference_no,
            'remarks' => $payment->remarks,
        ]);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }

    private function model(string $type): string
    {
        return match ($type) {
            'borrowers' => Borrower::class, 'loans' => Loan::class, 'payments' => Payment::class,
            default => abort(404),
        };
    }
}
