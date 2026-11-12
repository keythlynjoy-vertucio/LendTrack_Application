<?php
namespace App\Services;
use App\Models\Loan;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CalendarLoanService
{
    public function terms(string $start, string $first, string $frequency, int $months, float $principal, float $rate): array
    {
        $begin = Carbon::parse($start)->startOfDay();
        $end = $begin->copy()->addMonthsNoOverflow($months);
        $anchor = Carbon::parse($first)->startOfDay();
        if ($months < 1 || $months > 60 || $anchor->lte($begin) || $anchor->gt($end)) {
            throw ValidationException::withMessages(['first_due_date' => 'First due date must be after the start and on or before the calendar maturity date.']);
        }
        $dates = [];
        for ($i = 0; $i < 2000; $i++) {
            $date = match ($frequency) {
                'daily' => $anchor->copy()->addDays($i),
                'weekly' => $anchor->copy()->addWeeks($i),
                'semi-monthly' => $anchor->copy()->addDays($i * 15),
                'monthly' => $anchor->day === $anchor->daysInMonth
                    ? $anchor->copy()->addMonthsNoOverflow($i)->endOfMonth()->startOfDay()
                    : $anchor->copy()->addMonthsNoOverflow($i),
                default => throw ValidationException::withMessages(['frequency' => 'Invalid frequency.']),
            };
            if ($date->gte($end)) { break; }
            $dates[] = $date->toDateString();
        }
        // Any last short period is due at maturity, never after the loan term.
        $dates[] = $end->toDateString();
        $principalCents = (int) round($principal * 100);
        $rateBasisPoints = (int) round($rate * 100);
        $interestCents = (int) round($principalCents * $rateBasisPoints * $months / 10000);
        $total = ($principalCents + $interestCents) / 100;
        if ($total > 9999999999.99) {
            throw ValidationException::withMessages(['principal_amount' => 'Calculated total exceeds supported amount.']);
        }
        return ['interest_months' => $months, 'schedule_dates' => $dates,
            'term_months' => count($dates), 'due_date' => $end->toDateString(), 'total_payable' => $total];
    }

    public function quote(Loan $loan, string $date, ?LoanMetricsService $metrics = null): array
    {
        $metrics ??= new LoanMetricsService();
        $asOf = Carbon::parse($date)->startOfDay();
        $loan->loadMissing('payments');
        $schedule = $metrics->collectionSchedule($loan, $asOf);
        $payments = $loan->payments
            ->filter(fn ($payment) => $payment->paid_at && $payment->paid_at->toDateString() <= $date)
            ->sortBy([['paid_at', 'asc'], ['id', 'asc']])->values();
        $paymentIndex = 0;
        $onTimeCents = 0;
        $expectedCents = 0; $missed = 0; $suggestion = 0; $due = null;
        foreach ($schedule as $row) {
            $expectedCents += (int) round($row['expected'] * 100);
            if ($row['due_date'] < $date) {
                // Sorted due dates and payments permit one pass instead of
                // filtering every payment again for every installment.
                while ($paymentIndex < $payments->count()
                    && $payments[$paymentIndex]->paid_at->toDateString() <= $row['due_date']) {
                    $onTimeCents += (int) round((float) $payments[$paymentIndex]->amount * 100);
                    $paymentIndex++;
                }
                if ($onTimeCents < $expectedCents) { $missed++; }
            }
            if ($suggestion === 0 && $row['balance'] > 0) {
                $suggestion = (int) round($row['balance'] * 100); $due = $row['due_date'];
            }
        }
        $charged = $missed * (int) round((float) $loan->penalty_per_missed_due * 100);
        $penaltyPaid = (int) round($payments->sum('penalty_paid') * 100);
        return ['as_of' => $date, 'suggested_regular' => $suggestion / 100,
            'installment_due_date' => $due, 'missed_installments' => $missed,
            'penalty_charged' => $charged / 100, 'penalty_paid' => $penaltyPaid / 100,
            'penalty_outstanding' => max(0, $charged - $penaltyPaid) / 100,
            'regular_outstanding' => max(0, (int) round((float) $loan->total_payable * 100) - (int) round($payments->sum('amount') * 100)) / 100];
    }
}
