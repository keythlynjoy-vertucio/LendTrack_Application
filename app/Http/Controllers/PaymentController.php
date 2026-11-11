<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentRecorded;
use App\Services\LoanMetricsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $loanId = $request->query('loan_id');
        $from = $request->query('from');
        $to = $request->query('to');

        $sort = $request->query('sort', 'paid_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['paid_at', 'amount', 'created_at', 'method'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'paid_at';
        }

        $payments = Payment::query()
            ->with(['loan.borrower'])
            ->when(is_numeric($loanId), fn ($q) => $q->where('loan_id', (int) $loanId))
            ->when($from, fn ($q) => $q->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('paid_at', '<=', $to))
            ->orderBy($sort, $dir)
            ->paginate(10)
            ->withQueryString();

        $loans = Loan::query()
            ->with('borrower')
            ->latest()
            ->limit(200)
            ->get();

        return view('payments.index', compact('payments', 'loans', 'loanId', 'from', 'to', 'sort', 'dir'));
    }

    public function penaltyDue(Request $request)
    {
        $validated = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'payment_date' => ['required', 'date'],
        ]);

        $loan = Loan::findOrFail($validated['loan_id']);
        $paymentDate = Carbon::parse($validated['payment_date']);

        $terms = $this->schedulePeriodCount((int) $loan->term_months, $loan->frequency ?? 'monthly');
        $installment = $terms > 0 ? round((float) $loan->total_payable / $terms, 2) : 0.0;

        $startDate = $loan->first_due_date ?? $loan->start_date ?? $loan->created_at;
        $startDate = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);

        $payments = Payment::where('loan_id', $loan->id)
            ->whereDate('paid_at', '<=', $paymentDate->toDateString())
            ->orderBy('paid_at')
            ->get()
            ->map(function ($payment) {
                return [
                    'remaining' => (float) $payment->amount,
                    'paid_at' => $payment->paid_at ? Carbon::parse($payment->paid_at) : null,
                ];
            })
            ->values()
            ->all();

        $paymentIndex = 0;
        $missedDues = 0;

        for ($index = 0; $index < $terms; $index++) {
            $dueDate = $this->addFrequency($startDate, $index, $loan->frequency ?? 'monthly');
            $actualPaid = 0.0;
            $tempPaymentIndex = $paymentIndex;

            while ($tempPaymentIndex < count($payments) && $actualPaid < $installment) {
                $payment = &$payments[$tempPaymentIndex];
                $take = min($installment - $actualPaid, $payment['remaining']);
                $actualPaid += $take;
                $payment['remaining'] -= $take;

                if ($payment['remaining'] <= 0.00001) {
                    $tempPaymentIndex++;
                } else {
                    break;
                }
            }

            while ($paymentIndex < $tempPaymentIndex) {
                $paymentIndex++;
            }

            $balance = round(max(0, $installment - $actualPaid), 2);

            if ($balance > 0 && $dueDate->lt($paymentDate)) {
                $missedDues++;
            }
        }

        $penaltyDue = round($missedDues * (float) $loan->penalty_per_missed_due, 2);
        $unpaidDueTotal = round($missedDues * $installment, 2);

        return response()->json([
            'penalty_due' => $penaltyDue,
            'missed_dues' => $missedDues,
            'installment' => $installment,
            'unpaid_due_total' => $unpaidDueTotal,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        abort_unless(
            $request->user()?->hasPermission('record_payments') || $request->user()?->isAdmin(),
            403
        );

        $loanId = $request->query('loan_id') ?? null;

        $loans = Loan::query()
            ->with('borrower')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return view('payments.create', compact('loans', 'loanId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        abort_unless(
            $request->user()?->hasPermission('record_payments') || $request->user()?->isAdmin(),
            403
        );

        $validated = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'penalty_paid' => ['nullable', 'numeric', 'min:0'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', Rule::in(['cash', 'gcash', 'bank', 'manual'])],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $validated['penalty_paid'] = isset($validated['penalty_paid']) ? round((float) $validated['penalty_paid'], 2) : 0.00;
        $validated['user_id'] = $request->user()->id;

        $payment = Payment::create($validated);

        $this->recalculateLoanTotals($payment->loan);

        Notification::send(User::query()->where('role', 'admin')->get(), new PaymentRecorded($payment));

        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => Payment::class,
            'subject_id' => $payment->id,
            'action' => 'created',
            'description' => 'Recorded payment for loan '.$payment->loan->reference_no,
            'changes' => $validated,
        ]);

        return redirect()->route('payments.receipt', $payment)->with('status', 'Payment recorded.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment)
    {
        $payment->load(['loan.borrower', 'user']);

        return view('payments.show', compact('payment'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Payment $payment)
    {
        abort_unless(
            $request->user()?->hasPermission('manage_payments') || $request->user()?->isAdmin(),
            403
        );

        $payment->load(['loan.borrower']);
        $loans = Loan::query()->with('borrower')->latest()->limit(200)->get();

        return view('payments.edit', compact('payment', 'loans'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        abort_unless(
            $request->user()?->hasPermission('manage_payments') || $request->user()?->isAdmin(),
            403
        );

        $validated = $request->validate([
            'loan_id' => ['required', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'penalty_paid' => ['nullable', 'numeric', 'min:0'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', Rule::in(['cash', 'gcash', 'bank', 'manual'])],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $validated['penalty_paid'] = isset($validated['penalty_paid']) ? round((float) $validated['penalty_paid'], 2) : 0.00;
        $oldLoan = $payment->loan;

        // Track only the fields that actually changed
        $changes = [];
        foreach ($validated as $key => $value) {
            $originalValue = $payment->getAttribute($key);
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

        $payment->update($validated);
        $payment->refresh()->load('loan');

        $this->recalculateLoanTotals($payment->loan);
        if ($oldLoan && $oldLoan->id !== $payment->loan_id) {
            $this->recalculateLoanTotals($oldLoan);
        }

        // Only log if there are actual changes
        if (! empty($changes)) {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'subject_type' => Payment::class,
                'subject_id' => $payment->id,
                'action' => 'updated',
                'description' => 'Updated payment #'.$payment->id.' for loan '.$payment->loan->reference_no,
                'changes' => $changes,
            ]);
        }

        return redirect()->route('payments.show', $payment)->with('status', 'Payment updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Payment $payment)
    {
        abort_unless(
            $request->user()?->hasPermission('manage_payments') || $request->user()?->isAdmin(),
            403
        );

        $loan = $payment->loan;
        $payment->delete();

        if ($loan) {
            $this->recalculateLoanTotals($loan);
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => Payment::class,
            'subject_id' => $payment->id,
            'action' => 'deleted',
            'description' => 'Soft deleted payment #'.$payment->id.' for loan '.$loan?->reference_no,
        ]);

        return redirect()->route('payments.index')->with('status', 'Payment deleted.');
    }

    public function receipt(Payment $payment)
    {
        $payment->load(['loan.borrower', 'user']);

        return view('payments.receipt', compact('payment'));
    }

    private function recalculateLoanTotals(?Loan $loan): void
    {
        if (! $loan) {
            return;
        }

        (new LoanMetricsService())->syncLoanTotals($loan);
    }

    private function schedulePeriodCount(int $termMonths, string $frequency): int
    {
        // termMonths represents number of installments (terms)
        return max(1, $termMonths);
    }

    private function addFrequency(Carbon $date, int $index, string $frequency): Carbon
    {
        return match ($frequency) {
            'daily' => $date->copy()->addDays($index),
            'weekly' => $date->copy()->addWeeks($index),
            'semi-monthly' => $date->copy()->addDays($index * 15),
            default => $date->copy()->addMonths($index),
        };
    }
}
