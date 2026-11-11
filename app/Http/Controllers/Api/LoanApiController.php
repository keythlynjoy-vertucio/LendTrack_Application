<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Services\LoanMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LoanApiController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'overdue', 'paid', 'closed', 'cancelled', 'written_off'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($data['search'] ?? '');
        $loans = Loan::with('borrower')
            ->when(!empty($data['status']), fn ($q) => $q->whereRaw('LOWER(TRIM(status)) = ?', [$data['status']]))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('reference_no', 'like', "%{$search}%")
                        ->orWhereHas('borrower', function ($b) use ($search) {
                            $b->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                });
            })->latest()->orderByDesc('id')->paginate(10)->withQueryString();

        return response()->json($loans);
    }

    public function store(Request $request)
    {
        abort_unless(
            $request->user()?->isAdmin()
                || $request->user()?->hasPermission('create_loans'),
            403
        );

        $data = $request->validate([
            'borrower_id' => [
                'required',
                'integer',
                Rule::exists('borrowers', 'id')
                    ->whereNull('deleted_at'),
            ],
            'start_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'first_due_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:start_date',
            ],
            'principal_amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999999.99',
            ],
            'interest_rate' => [
                'required',
                'numeric',
                'min:0',
                'max:999.99',
            ],
            'term_months' => [
                'required',
                'integer',
                'min:1',
                'max:600',
            ],
            'frequency' => [
                'required',
                Rule::in([
                    'daily',
                    'weekly',
                    'semi-monthly',
                    'monthly',
                ]),
            ],
            'penalty_per_missed_due' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $principal = round((float) $data['principal_amount'], 2);
        $interestRate = round((float) $data['interest_rate'], 2);

        if ($principal < 0.01) {
            throw ValidationException::withMessages([
                'principal_amount' => [
                    'Principal must be at least 0.01.',
                ],
            ]);
        }

        // Same single-interest computation used by the website.
        $totalPayable = round(
            $principal + ($principal * ($interestRate / 100)),
            2
        );

        if ($totalPayable > 9999999999.99) {
            throw ValidationException::withMessages([
                'principal_amount' => [
                    'The calculated total payable exceeds the supported amount.',
                ],
            ]);
        }

        $metrics = new LoanMetricsService();

        $data['principal_amount'] = $principal;
        $data['interest_rate'] = $interestRate;
        $data['penalty_per_missed_due'] = round(
            (float) $data['penalty_per_missed_due'],
            2
        );

        // Despite its name, the service treats term_months
        // as the number of installments.
        $data['term_months'] = (int) $data['term_months'];

        $data['total_payable'] = $totalPayable;
        $data['total_paid'] = 0;
        $data['status'] = 'active';
        $data['user_id'] = $request->user()->id;

        $data['due_date'] = $metrics->calculateFinalDueDate(
            $data['first_due_date'],
            $data['frequency'],
            $data['term_months']
        );

        $loan = DB::transaction(function () use (
            $request,
            $data,
            $metrics
        ) {
            // Recheck the borrower inside the transaction.
            $borrower = Borrower::query()
                ->whereKey($data['borrower_id'])
                ->lockForUpdate()
                ->first();

            if (! $borrower) {
                throw ValidationException::withMessages([
                    'borrower_id' => [
                        'This borrower is no longer available.',
                    ],
                ]);
            }

            $data['reference_no'] = $this->generateReferenceNo();

            $loan = Loan::create($data);

            $metrics->syncLoanTotals($loan);

            ActivityLog::create([
                'user_id' => $request->user()->id,
                'subject_type' => Loan::class,
                'subject_id' => $loan->id,
                'action' => 'created',
                'description' => 'Created loan '
                    . $loan->reference_no
                    . ' for borrower #'
                    . $loan->borrower_id,
                'changes' => array_merge($data, [
                    'status' => $loan->status,
                    'total_paid' => $loan->total_paid,
                ]),
            ]);

            return $loan;
        });

        $loan->load('borrower');

        return response()->json([
            'message' => 'Loan created successfully.',
            'data' => $loan,
        ], 201);
    }

    public function show(Loan $loan)
    {
        $loan->load(['borrower', 'payments']);

        return response()->json($loan);
    }

    public function payments(Loan $loan)
    {
        $payments = $loan->payments()
            ->latest('paid_at')
            ->get();

        return response()->json([
            'loan_id' => $loan->id,
            'reference_no' => $loan->reference_no,
            'payments' => $payments,
        ]);
    }

    private function generateReferenceNo(): string
    {
        do {
            $reference = 'LN-'
                . now()->format('Ymd')
                . '-'
                . Str::upper(Str::random(6));
        } while (
            Loan::withTrashed()
                ->where('reference_no', $reference)
                ->exists()
        );

        return $reference;
    }
}