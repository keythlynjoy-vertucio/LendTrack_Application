<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\LoanStatusNotification;
use App\Services\LoanMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $sort = $request->query('sort', 'created_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['created_at', 'due_date', 'status', 'principal_amount', 'total_payable', 'total_paid'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $loans = Loan::query()
            ->with('borrower')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('reference_no', 'like', "%{$q}%")
                        ->orWhereHas('borrower', function ($b) use ($q) {
                            $b->where('first_name', 'like', "%{$q}%")
                                ->orWhere('last_name', 'like', "%{$q}%")
                                ->orWhere('phone', 'like', "%{$q}%");
                        });
                });
            })
            ->when(in_array($status, ['pending', 'active', 'paid', 'overdue', 'closed', 'cancelled'], true), fn ($query) => $query->where('status', $status))
            ->orderBy($sort, $dir)
            ->paginate(10)
            ->withQueryString();

        return view('loans.index', compact('loans', 'q', 'status', 'sort', 'dir'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        abort_unless(
            $request->user()?->hasPermission('create_loans') || $request->user()?->isAdmin(),
            403
        );

        $borrowers = Borrower::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $borrowerId = $request->query('borrower_id');

        return view('loans.create', compact('borrowers', 'borrowerId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(
            $request->user()?->hasPermission('create_loans') || $request->user()?->isAdmin(),
            403
        );

        $validated = $request->validate([
            'borrower_id' => ['required', 'exists:borrowers,id'],
            'start_date' => ['required', 'date'],
            'first_due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'principal_amount' => ['required', 'numeric', 'min:0.01'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'semi-monthly', 'monthly'])],
            'penalty_per_missed_due' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
        $validated['reference_no'] = $this->generateReferenceNo();

        $principal = (float) $validated['principal_amount'];
        $interestRate = (float) $validated['interest_rate'];
        $metrics = new LoanMetricsService();
        $validated['total_payable'] = round($principal + ($principal * ($interestRate / 100)), 2);
        $validated['total_paid'] = 0;
        $validated['status'] = 'active';
        $validated['due_date'] = $metrics->calculateFinalDueDate($validated['first_due_date'], $validated['frequency'], $validated['term_months']);
        $validated['user_id'] = auth()->id();

        $loan = Loan::create($validated);
        $metrics->syncLoanTotals($loan);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => Loan::class,
            'subject_id' => $loan->id,
            'action' => 'created',
            'description' => 'Created loan '.$loan->reference_no.' for borrower #'.$loan->borrower_id,
            'changes' => $validated,
        ]);

        return redirect()->route('loans.show', $loan)->with('status', 'Loan created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Loan $loan)
    {
        $loan->load(['borrower', 'payments' => fn ($q) => $q->latest(), 'user']);

        $metrics = new LoanMetricsService();
        $outstanding = $metrics->calculateLoanOutstandingBalance($loan);

        return view('loans.show', compact('loan', 'outstanding'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Loan $loan)
    {
        abort_unless(
            $request->user()?->hasPermission('manage_loans') || $request->user()?->isAdmin(),
            403
        );

        $loan->load('borrower');
        $borrowers = Borrower::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('loans.edit', compact('loan', 'borrowers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Loan $loan)
    {
        abort_unless(
            $request->user()?->hasPermission('manage_loans') || $request->user()?->isAdmin(),
            403
        );

        $validated = $request->validate([
            'borrower_id' => ['required', 'exists:borrowers,id'],
            'start_date' => ['required', 'date'],
            'first_due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'principal_amount' => ['required', 'numeric', 'min:0.01'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'semi-monthly', 'monthly'])],
            'penalty_per_missed_due' => ['required', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $principal = (float) $validated['principal_amount'];
        $interestRate = (float) $validated['interest_rate'];
        $metrics = new LoanMetricsService();
        $validated['total_payable'] = round($principal + ($principal * ($interestRate / 100)), 2);
        $validated['due_date'] = $metrics->calculateFinalDueDate($validated['first_due_date'], $validated['frequency'], $validated['term_months']);

        // Track only the fields that actually changed
        $changes = [];
        foreach ($validated as $key => $value) {
            $originalValue = $loan->getAttribute($key);
            // Handle date comparisons
            if ($originalValue instanceof \DateTime) {
                $originalValue = $originalValue->format('Y-m-d');
            }
            if ($originalValue !== $value) {
                $changes[$key] = [
                    'from' => $originalValue,
                    'to' => $value,
                ];
            }
        }

        $loan->update($validated);
        $metrics->syncLoanTotals($loan);

        // Only log if there are actual changes
        if (! empty($changes)) {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'subject_type' => Loan::class,
                'subject_id' => $loan->id,
                'action' => 'updated',
                'description' => 'Updated loan '.$loan->reference_no,
                'changes' => $changes,
            ]);
        }

        return redirect()->route('loans.show', $loan)->with('status', 'Loan updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Loan $loan)
    {
        abort_unless(
            $request->user()?->hasPermission('manage_loans') || $request->user()?->isAdmin(),
            403
        );

        $loan->delete();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => Loan::class,
            'subject_id' => $loan->id,
            'action' => 'deleted',
            'description' => 'Soft deleted loan '.$loan->reference_no,
        ]);

        return redirect()->route('loans.index')->with('status', 'Loan deleted.');
    }

    public function trashed()
    {
        $loans = Loan::onlyTrashed()->with('borrower')->paginate(10);

        return view('loans.trashed', compact('loans'));
    }

    public function restore(Request $request, int $loanId)
    {
        abort_unless(
            $request->user()?->hasPermission('manage_loans') || $request->user()?->isAdmin(),
            403
        );

        $loan = Loan::onlyTrashed()->findOrFail($loanId);
        $loan->restore();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => Loan::class,
            'subject_id' => $loan->id,
            'action' => 'restored',
            'description' => 'Restored loan '.$loan->reference_no,
        ]);

        return redirect()->route('loans.index')->with('status', 'Loan restored.');
    }

    public function close(Loan $loan, Request $request)
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        if (strtolower($loan->status) === 'paid') {
            return redirect()->back()->with('error', 'Only non-paid loans can be closed.');
        }

        $validated = $request->validate([
            'close_reason' => [
                'required',
                Rule::in([
                    'fully_paid_not_updated',
                    'settled_outside',
                    'cancelled_terminated',
                    'written_off',
                ]),
            ],
            'close_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        // Map close reason to status
        $statusMap = [
            'fully_paid_not_updated' => 'Paid',
            'settled_outside' => 'Closed',
            'cancelled_terminated' => 'Cancelled',
            'written_off' => 'Written Off',
        ];

        $newStatus = $statusMap[$validated['close_reason']];

        $loan->update([
            'status' => $newStatus,
            'closed_at' => now(),
            'closed_by' => auth()->id(),
            'close_reason' => $validated['close_reason'],
            'close_remarks' => $validated['close_remarks'] ?? null,
        ]);

        $reasonLabels = [
            'fully_paid_not_updated' => 'Fully Paid but Not Auto Updated',
            'settled_outside' => 'Settled Outside the System',
            'cancelled_terminated' => 'Cancelled or Terminated',
            'written_off' => 'Written Off',
        ];

        ActivityLog::create([
            'user_id' => auth()->id(),
            'role' => auth()->user()->role,
            'action' => 'Updated',
            'subject_type' => 'Loan',
            'subject_id' => $loan->id,
            'description' => 'Closed loan ' . $loan->reference_no . ' with reason: ' . $reasonLabels[$validated['close_reason']],
        ]);

        // Send notifications to all admin users
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new LoanStatusNotification($loan, 'closed', auth()->user()));
        }

        return redirect()->route('loans.show', $loan)->with('success', 'Loan closed successfully.');
    }

    public function unclose(Loan $loan)
    {
        abort_unless(auth()->user()->role === 'admin', 403);

        if (strtolower($loan->status) !== 'closed') {
            return back()->with('error', 'Only closed loans can be reopened.');
        }

        $loan->update([
            'status' => 'Active',
            'closed_at' => null,
            'closed_by' => null,
            'close_reason' => null,
            'close_remarks' => null,
        ]);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'role' => auth()->user()->role,
            'action' => 'Updated',
            'subject_type' => 'Loan',
            'subject_id' => $loan->id,
            'description' => 'Reopened loan ' . $loan->reference_no,
        ]);

        // Send notifications to all admin users
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new LoanStatusNotification($loan, 'unclosed', auth()->user()));
        }

        return redirect()->route('loans.show', $loan)->with('success', 'Loan reopened successfully.');
    }

    private function generateReferenceNo(): string
    {
        do {
            $ref = 'LN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Loan::where('reference_no', $ref)->exists());

        return $ref;
    }

}
