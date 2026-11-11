<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\Borrower;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoanMetricsService
{
    protected array $statusOrder = [
        'active',
        'paid',
        'overdue',
        'due today',
        'upcoming',
        'closed',
        'cancelled',
        'pending',
    ];

    protected bool $hasPenaltyColumn;

    public function __construct()
    {
        $this->hasPenaltyColumn = Schema::hasColumn('payments', 'penalty_paid');
    }

    public function hasPenaltyColumn(): bool
    {
        return $this->hasPenaltyColumn;
    }

    public function getReleasedCapital(): float
    {
        return (float) Loan::whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?, ?)', ['cancelled', 'pending', 'closed', 'written_off'])->sum('principal_amount');
    }

    public function getCollectionsTotals(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = Payment::query();

        if ($from) {
            $query->whereDate('paid_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('paid_at', '<=', $to);
        }

        $regular = (float) (clone $query)
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->value('total');

        $penalty = $this->hasPenaltyColumn
            ? (float) (clone $query)
                ->selectRaw('COALESCE(SUM(COALESCE(penalty_paid, 0)), 0) as total')
                ->value('total')
            : 0.0;

        return [
            'regular' => $regular,
            'penalty' => $penalty,
            'total' => $regular + $penalty,
        ];
    }

    public function getPortfolioTotals(?Carbon $today = null): array
    {
        $today = $today ?: Carbon::today();

        $summary = [
            'released_capital' => $this->getReleasedCapital(),
            'regular_payments' => 0.0,
            'penalty_payments' => 0.0,
            'total_collections' => 0.0,
            'outstanding_balance' => 0.0,
            'overdue_amount' => 0.0,
            'due_today' => 0.0,
            'due_this_week' => 0.0,
            'due_this_week_count' => 0,
            'upcoming_amount' => 0.0,
            'loan_status_count' => $this->getLoanStatusCounts(),
        ];

        $collections = $this->getCollectionsTotals();
        $summary['regular_payments'] = $collections['regular'];
        $summary['penalty_payments'] = $collections['penalty'];
        $summary['total_collections'] = $collections['total'];

        $loans = Loan::with(['borrower', 'payments'])
            ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?, ?)', ['pending', 'cancelled', 'closed', 'written_off'])
            ->whereHas('borrower', fn ($query) => $query->whereNull('deleted_at'))
            ->get();

        foreach ($loans as $loan) {
            $schedule = $this->allocatePaymentsToSchedule(
                $this->buildLoanPaymentSchedule($loan),
                $loan->payments,
                $today
            );

            foreach ($schedule as $item) {
                if ($item['balance'] <= 0) {
                    continue;
                }

                $summary['outstanding_balance'] += $item['balance'];

                if ($item['due_date']->lt($today)) {
                    $summary['overdue_amount'] += $item['balance'];
                    continue;
                }

                if ($item['due_date']->isSameDay($today)) {
                    $summary['due_today'] += $item['balance'];
                }

                if ($item['due_date']->between($today, $today->copy()->addDays(7))) {
                    $summary['due_this_week'] += $item['balance'];
                    $summary['due_this_week_count']++;
                }

                if ($item['due_date']->gt($today)) {
                    $summary['upcoming_amount'] += $item['balance'];
                }
            }
        }

        foreach ([
            'released_capital',
            'regular_payments',
            'penalty_payments',
            'total_collections',
            'outstanding_balance',
            'overdue_amount',
            'due_today',
            'due_this_week',
            'upcoming_amount',
        ] as $key) {
            $summary[$key] = round($summary[$key], 2);
        }

        return $summary;
    }

    public function getLoanStatusCounts(?Carbon $from = null, ?Carbon $to = null): array
    {
        // Always count all loans by status, regardless of date filters
        // Date filters are for payment-based reports, not for status counts
        $counts = Loan::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return $this->sortStatusCounts($counts);
    }

    public function getMonthlyCollections(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = Payment::query()
            ->selectRaw($this->monthSelectExpression())
            ->selectRaw('SUM(amount' . ($this->hasPenaltyColumn ? ' + COALESCE(penalty_paid, 0)' : '') . ') as total');

        if ($from) {
            $query->whereDate('paid_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('paid_at', '<=', $to);
        }

        return $query->groupBy('month')
            ->orderBy('month')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->month => (float) $item->total])
            ->toArray();
    }

    public function getDailyCollections(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = Payment::query()
            ->selectRaw($this->daySelectExpression('paid_at') . ' as day')
            ->selectRaw('SUM(amount' . ($this->hasPenaltyColumn ? ' + COALESCE(penalty_paid, 0)' : '') . ') as total');

        if ($from) {
            $query->whereDate('paid_at', '>=', $from);
        }
        if ($to) {
            $query->whereDate('paid_at', '<=', $to);
        }

        return $query->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->day => (float) $item->total])
            ->toArray();
    }

    public function getDailyReleasesRange(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = Loan::query()
            ->selectRaw($this->daySelectExpression('start_date') . ' as day')
            ->selectRaw('SUM(principal_amount) as total')
            ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?, ?)', ['cancelled', 'pending', 'closed', 'written_off']);

        if ($from) {
            $query->whereDate('start_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('start_date', '<=', $to);
        }

        return $query->groupBy('day')
            ->orderBy('day')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->day => (float) $item->total])
            ->toArray();
    }

    public function getRecentMonthlyCollections(int $months = 6, ?Carbon $today = null): array
    {
        $today = $today ?: Carbon::today();
        $from = $today->copy()->subMonths($months - 1)->startOfMonth();
        $to = $today->copy()->endOfMonth();

        $rows = Payment::query()
            ->selectRaw($this->monthSelectExpression())
            ->selectRaw('SUM(amount' . ($this->hasPenaltyColumn ? ' + COALESCE(penalty_paid, 0)' : '') . ') as total')
            ->whereBetween('paid_at', [$from, $to])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $totals = $rows->mapWithKeys(fn ($item) => [$item->month => (float) $item->total])->toArray();

        return collect($this->getRecentMonths($months, $today))
            ->mapWithKeys(fn ($month) => [$month => $totals[$month] ?? 0.0])
            ->toArray();
    }

    public function getMonthlyReleases(): array
    {
        $rows = Loan::query()
            ->selectRaw(match (DB::connection()->getDriverName()) {
                'sqlite' => "strftime('%Y-%m', start_date) as month",
                'pgsql' => "to_char(start_date, 'YYYY-MM') as month",
                default => "DATE_FORMAT(start_date, '%Y-%m') as month",
            })
            ->selectRaw('SUM(principal_amount) as total')
            ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?, ?)', ['cancelled', 'pending', 'closed', 'written_off'])
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $rows->mapWithKeys(fn ($item) => [$item->month => (float) $item->total])->toArray();
    }

    public function getMonthlyReleasesRange(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = Loan::query()
            ->selectRaw(match (DB::connection()->getDriverName()) {
                'sqlite' => "strftime('%Y-%m', start_date) as month",
                'pgsql' => "to_char(start_date, 'YYYY-MM') as month",
                default => "DATE_FORMAT(start_date, '%Y-%m') as month",
            })
            ->selectRaw('SUM(principal_amount) as total')
            ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?, ?)', ['cancelled', 'pending', 'closed', 'written_off']);

        if ($from) {
            $query->whereDate('start_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('start_date', '<=', $to);
        }

        $rows = $query->groupBy('month')
            ->orderBy('month')
            ->get();

        return $rows->mapWithKeys(fn ($item) => [$item->month => (float) $item->total])->toArray();
    }

    public function getTotalPayable(): float
    {
        return Loan::query()
            ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?, ?)', ['cancelled', 'pending', 'closed', 'written_off'])
            ->get()
            ->reduce(fn ($sum, Loan $loan) => $sum + $this->loanTotalPayable($loan), 0.0);
    }

    public function getOutstandingBalance(): float
    {
        return $this->getPortfolioTotals()['outstanding_balance'];
    }

    public function getOverdueAmount(): float
    {
        return $this->getPortfolioTotals()['overdue_amount'];
    }

    public function getUpcomingSummary(int $limit = 5, ?Carbon $today = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $today = $today ?: Carbon::today();
        $startDate = $startDate ?: Carbon::today()->startOfMonth();
        $endDate = $endDate ?: Carbon::today()->endOfMonth();

        $loans = Loan::with(['borrower', 'payments'])
            ->whereIn('status', ['active', 'overdue'])
            ->whereHas('borrower', fn ($query) => $query->whereNull('deleted_at'))
            ->get();

        $collections = [];
        $summary = [
            'overdue' => 0.0,
            'due_today' => 0.0,
            'upcoming' => 0.0,
            'due_this_week' => 0.0,
            'due_this_week_count' => 0,
            'total_outstanding' => 0.0,
        ];

        foreach ($loans as $loan) {
            $schedule = $this->allocatePaymentsToSchedule(
                $this->buildLoanPaymentSchedule($loan),
                $loan->payments,
                $today
            );

            foreach ($schedule as $item) {
                if ($item['balance'] <= 0) {
                    continue;
                }

                if ($item['due_date']->lt($startDate) || $item['due_date']->gt($endDate)) {
                    continue;
                }

                $status = $this->classifyScheduleStatus($item['due_date'], $item['balance'], $today);

                $collections[] = [
                    'due_date' => $item['due_date']->toDateString(),
                    'borrower_name' => $loan->borrower ? ($loan->borrower->last_name . ', ' . $loan->borrower->first_name) : 'Unknown borrower',
                    'loan_reference' => $loan->reference_no,
                    'installment_balance' => $item['balance'],
                    'installment_amount' => $item['expected'],
                    'amount_paid' => $item['actual_paid'],
                    'status' => $status,
                    'loan_id' => $loan->id,
                ];

                $summary['total_outstanding'] += $item['balance'];

                if ($item['due_date']->lt($today)) {
                    $summary['overdue'] += $item['balance'];
                }

                if ($item['due_date']->isSameDay($today)) {
                    $summary['due_today'] += $item['balance'];
                }

                if ($item['due_date']->gt($today)) {
                    $summary['upcoming'] += $item['balance'];
                }

                if ($item['due_date']->between($today, $today->copy()->addDays(7))) {
                    $summary['due_this_week'] += $item['balance'];
                    $summary['due_this_week_count']++;
                }
            }
        }

        usort($collections, fn ($a, $b) => $a['due_date'] <=> $b['due_date']);

        return [
            'items' => array_slice($collections, 0, $limit),
            'summary' => $summary,
        ];
    }

    public function getTopBorrowersByOutstanding(int $limit = 10): array
    {
        return Borrower::with(['loans.payments'])
            ->get()
            ->map(function ($borrower) {
                $outstanding = 0.0;

                foreach ($borrower->loans as $loan) {
                    if (in_array(strtolower(trim($loan->status)), ['pending', 'cancelled', 'closed', 'written_off'], true)) {
                        continue;
                    }

                    $schedule = $this->allocatePaymentsToSchedule($this->buildLoanPaymentSchedule($loan), $loan->payments, Carbon::today());
                    foreach ($schedule as $item) {
                        if ($item['balance'] > 0) {
                            $outstanding += $item['balance'];
                        }
                    }
                }

                return [
                    'borrower' => trim(($borrower->last_name ? $borrower->last_name . ', ' : '') . ($borrower->first_name ?? '')),
                    'remaining' => round($outstanding, 2),
                ];
            })
            ->sortByDesc('remaining')
            ->values()
            ->take($limit)
            ->toArray();
    }

    public function getCutoffBuckets(?Carbon $today = null): array
    {
        $today = $today ?: Carbon::today();

        $buckets = [
            'Next 7 days' => 0.0,
            '8-14 days' => 0.0,
            '15-30 days' => 0.0,
            'Over 30 days' => 0.0,
        ];

        $loans = Loan::with('payments')
            ->whereRaw('LOWER(TRIM(status)) NOT IN (?, ?, ?, ?)', ['pending', 'cancelled', 'closed', 'written_off'])
            ->get();

        foreach ($loans as $loan) {
            $schedule = $this->allocatePaymentsToSchedule($this->buildLoanPaymentSchedule($loan), $loan->payments, $today);

            foreach ($schedule as $item) {
                if ($item['balance'] <= 0) {
                    continue;
                }

                $diff = $today->diffInDays($item['due_date'], false);
                if ($diff < 0) {
                    continue;
                }

                if ($diff <= 7) {
                    $buckets['Next 7 days'] += $item['balance'];
                } elseif ($diff <= 14) {
                    $buckets['8-14 days'] += $item['balance'];
                } elseif ($diff <= 30) {
                    $buckets['15-30 days'] += $item['balance'];
                } else {
                    $buckets['Over 30 days'] += $item['balance'];
                }
            }
        }

        return array_map(fn ($amount) => round($amount, 2), $buckets);
    }

    public function getOverdueByBorrower(?Carbon $from = null, ?Carbon $to = null, int $limit = 5): array
    {
        $today = Carbon::today();
        $cutoffStart = $from ? Carbon::parse($from) : null;
        $cutoffEnd = $to ? Carbon::parse($to) : $today;

        $loans = Loan::with(['borrower', 'payments'])
            ->whereIn('status', ['active', 'overdue'])
            ->whereHas('borrower', fn ($query) => $query->whereNull('deleted_at'))
            ->get();

        $overdue = [];

        foreach ($loans as $loan) {
            $schedule = $this->buildLoanPaymentSchedule($loan);
            $schedule = $this->allocatePaymentsToSchedule($schedule, $loan->payments, $cutoffEnd);

            $overdueTotal = 0.0;

            foreach ($schedule as $item) {
                if ($cutoffStart && $item['due_date']->lt($cutoffStart)) {
                    continue;
                }
                if ($item['due_date']->gt($cutoffEnd)) {
                    break;
                }
                if ($item['balance'] > 0 && $item['due_date']->lt($today)) {
                    $overdueTotal += $item['balance'];
                }
            }

            if ($overdueTotal > 0 && $loan->borrower) {
                $overdue[] = [
                    'borrower_name' => $loan->borrower->last_name . ', ' . $loan->borrower->first_name,
                    'loan_reference' => $loan->reference_no,
                    'overdue_amount' => round($overdueTotal, 2),
                    'action_url' => route('reports.discrepancy', array_filter([
                        'borrower_id' => $loan->borrower_id,
                        'loan_id' => $loan->id,
                    ])),
                ];
            }
        }

        usort($overdue, fn ($a, $b) => $b['overdue_amount'] <=> $a['overdue_amount']);

        return array_slice($overdue, 0, $limit);
    }

    public function buildDiscrepancySchedule(Loan $loan, Carbon $asOf): array
    {
        $schedule = $this->buildLoanPaymentSchedule($loan);
        $payments = $loan->payments()
            ->whereDate('paid_at', '<=', $asOf->toDateString())
            ->orderBy('paid_at')
            ->get();

        $schedule = $this->allocatePaymentsToSchedule($schedule, $payments, $asOf);

        $runningDifference = 0.0;
        $expectedPaidBy = 0.0;
        $rows = [];

        foreach ($schedule as $item) {
            $expected = $item['expected'];
            $actual = $item['actual_paid'];
            $difference = round($expected - $actual, 2);

            if ($item['due_date']->lte($asOf)) {
                $runningDifference = round($runningDifference + $difference, 2);
                $expectedPaidBy += $expected;
            }

            $remarks = 'Not included';
            if ($item['due_date']->lte($asOf)) {
                if ($actual >= $expected) {
                    if ($item['last_payment_date']) {
                        if ($item['last_payment_date']->lt($item['due_date'])) {
                            $daysEarly = $item['last_payment_date']->diffInDays($item['due_date']);
                            $remarks = "Advance ({$daysEarly} days early)";
                        } elseif ($item['last_payment_date']->gt($item['due_date'])) {
                            $daysLate = $item['due_date']->diffInDays($item['last_payment_date']);
                            $remarks = "Late ({$daysLate} days late)";
                        } else {
                            $remarks = 'On track';
                        }
                    } else {
                        $remarks = 'Paid';
                    }
                } elseif ($actual == 0.0) {
                    $remarks = 'Overdue';
                } else {
                    $remarks = 'Short payment';
                }
            }

            $rows[] = [
                'due_date' => $item['due_date']->toDateString(),
                'included_status' => $item['due_date']->lte($asOf) ? 'Included' : 'Not included',
                'actual_payment_date' => $item['last_payment_date'] ? $item['last_payment_date']->toDateString() : '-',
                'expected_due' => number_format($expected, 2),
                'actual_payment' => number_format($actual, 2),
                'difference' => number_format($difference, 2),
                'running_difference' => number_format($runningDifference, 2),
                'remarks' => $remarks,
            ];
        }

        $actualPaidBy = round($payments->sum('amount'), 2);
        $expectedPaidBy = round($expectedPaidBy, 2);

        return [
            'rows' => $rows,
            'summary' => [
                'expected_paid_by' => $expectedPaidBy,
                'actual_paid_by' => $actualPaidBy,
                'discrepancy' => round(max(0, $expectedPaidBy - $actualPaidBy), 2),
                'advance' => round(max(0, $actualPaidBy - $expectedPaidBy), 2),
                'total_payable' => round($this->loanTotalPayable($loan), 2),
                'schedule_count' => count($schedule),
                'installment' => count($schedule) ? $schedule[0]['expected'] : 0.0,
            ],
            'expected_paid_by' => $expectedPaidBy,
            'actual_paid_by' => $actualPaidBy,
            'discrepancy' => round(max(0, $expectedPaidBy - $actualPaidBy), 2),
            'advance' => round(max(0, $actualPaidBy - $expectedPaidBy), 2),
        ];
    }

    public function getLoanPaymentTotals(Loan $loan): array
    {
        $payments = $loan->relationLoaded('payments')
            ? $loan->payments
            : $loan->payments()->get();

        $regular = round((float) $payments->sum('amount'), 2);
        $penalty = $this->hasPenaltyColumn
            ? round((float) $payments->sum('penalty_paid'), 2)
            : 0.0;

        return [
            'regular' => $regular,
            'penalty' => $penalty,
            'total' => round($regular + $penalty, 2),
        ];
    }

    public function calculateLoanOutstandingBalance(Loan $loan, ?Carbon $today = null): float
    {
        $today = $today ?: Carbon::today();
        $payments = $loan->relationLoaded('payments')
            ? $loan->payments
            : $loan->payments()->get();

        $schedule = $this->allocatePaymentsToSchedule($this->buildLoanPaymentSchedule($loan), $payments, $today);

        return round(array_sum(array_column($schedule, 'balance')), 2);
    }

    public function calculateLoanStatus(Loan $loan, ?Carbon $today = null): string
    {
        $today = $today ?: Carbon::today();

        $status = strtolower(trim((string) $loan->status));
        if (in_array($status, ['cancelled', 'pending', 'closed', 'written_off'], true)) {
            return $status;
        }

        $paymentTotals = $this->getLoanPaymentTotals($loan);
        if ($paymentTotals['regular'] >= round($this->loanTotalPayable($loan), 2)) {
            return 'paid';
        }

        $finalDueDate = $this->getLoanFinalDueDate($loan);
        if ($finalDueDate && $finalDueDate->lt($today)) {
            return 'overdue';
        }

        return 'active';
    }

    public function syncLoanTotals(Loan $loan, ?Carbon $today = null): void
    {
        $loan->loadMissing('payments');

        $paymentTotals = $this->getLoanPaymentTotals($loan);
        $loan->total_paid = $paymentTotals['regular'];
        $loan->status = $this->calculateLoanStatus($loan, $today);
        $loan->save();
    }

    public function getLoanFinalDueDate(Loan $loan): ?Carbon
    {
        if ($loan->due_date) {
            return $loan->due_date instanceof Carbon ? $loan->due_date : Carbon::parse($loan->due_date);
        }

        $schedule = $this->buildLoanPaymentSchedule($loan);
        if (empty($schedule)) {
            return null;
        }

        return $schedule[array_key_last($schedule)]['due_date'];
    }

    public function calculateFinalDueDate(string $firstDueDate, string $frequency, int $termMonths): string
    {
        $dueDate = Carbon::parse($firstDueDate);
        // terms should represent number of installments based on frequency
        $terms = $this->schedulePeriodCount($termMonths, $frequency);

        if ($terms > 1) {
            $dueDate = $this->addFrequency($dueDate, $terms - 1, $frequency);
        }

        return $dueDate->format('Y-m-d');
    }

    public function calculateOutstandingBalance(Collection $loans, bool $allowFuture = true): float
    {
        return $this->sumLoanBalances($loans, $allowFuture, false);
    }

    public function calculateOverdueBalance(Collection $loans): float
    {
        return $this->sumLoanBalances($loans, true, true);
    }
        public function collectionSchedule(
        Loan $loan,
        ?Carbon $asOf = null
    ): array {
        $asOf = ($asOf ?? Carbon::today())->copy()->startOfDay();

        $payments = $loan->relationLoaded('payments')
            ? $loan->payments
            : $loan->payments()->orderBy('paid_at')->get();

        $schedule = $this->allocatePaymentsToSchedule(
            $this->buildLoanPaymentSchedule($loan),
            $payments->sortBy('paid_at'),
            $asOf->copy()->endOfDay()
        );

        return array_map(
            fn ($item) => [
                'due_date' => $item['due_date']->toDateString(),
                'expected' => $item['expected'],
                'actual_paid' => $item['actual_paid'],
                'balance' => $item['balance'],
            ],
            $schedule
        );
    }

    private function buildLoanPaymentSchedule(Loan $loan): array
    {
        // terms represent number of installments (period count) based on term_months and frequency
        $terms = $this->schedulePeriodCount((int) $loan->term_months, $loan->frequency ?? 'monthly');
        $frequency = $this->normalizeFrequency($loan->frequency ?? 'monthly');
        $totalPayable = $this->loanTotalPayable($loan);
        $installment = $terms > 0 ? round($totalPayable / $terms, 2) : 0.0;

        if ($terms > 1 && round($installment * ($terms - 1), 2) > $totalPayable) {
            $installment = floor(round($totalPayable * 100) / $terms) / 100;
        }

        $startDate = $loan->first_due_date ?? $loan->start_date ?? $loan->created_at ?? Carbon::today();
        if (! $startDate instanceof Carbon) {
            $startDate = Carbon::parse($startDate);
        }

        $schedule = [];
        for ($index = 0; $index < $terms; $index++) {
            $dueDate = $this->addFrequency($startDate, $index, $frequency);
            $expected = $index === $terms - 1
                ? round($totalPayable - $installment * ($terms - 1), 2)
                : $installment;

            $schedule[] = [
                'due_date' => $dueDate->startOfDay(),
                'expected' => $expected,
                'actual_paid' => 0.0,
                'balance' => $expected,
                'last_payment_date' => null,
            ];
        }

        return $schedule;
    }

    private function allocatePaymentsToSchedule(array $schedule, $payments, ?Carbon $cutoff = null): array
    {
        $payments = collect($payments)
            ->map(function ($payment) {
                return [
                    'remaining' => (float) $payment->amount,
                    'paid_at' => $payment->paid_at ? Carbon::parse($payment->paid_at) : null,
                    'date' => $payment->paid_at ? Carbon::parse($payment->paid_at) : null,
                ];
            })
            ->filter(fn ($payment) => ! $cutoff || ! $payment['paid_at'] || $payment['paid_at']->lte($cutoff))
            ->values()
            ->all();

        $paymentIndex = 0;

        foreach ($schedule as &$item) {
            $actualPaid = 0.0;
            $lastPaymentDate = null;

            while ($paymentIndex < count($payments) && $actualPaid < $item['expected']) {
                $payment = &$payments[$paymentIndex];
                if ($payment['remaining'] <= 0) {
                    $paymentIndex++;
                    continue;
                }

                $take = min($item['expected'] - $actualPaid, $payment['remaining']);
                $actualPaid += $take;
                $payment['remaining'] -= $take;

                if ($payment['paid_at']) {
                    $lastPaymentDate = $payment['paid_at'];
                }

                if ($payment['remaining'] <= 0.00001) {
                    $paymentIndex++;
                } else {
                    break;
                }
            }

            $item['actual_paid'] = round($actualPaid, 2);
            $item['balance'] = round(max(0, $item['expected'] - $item['actual_paid']), 2);
            $item['last_payment_date'] = $lastPaymentDate;
        }

        return $schedule;
    }

    private function sumLoanBalances(Collection $loans, bool $allowFuture = true, bool $onlyOverdue = false): float
    {
        $today = Carbon::today();
        $total = 0.0;

        foreach ($loans as $loan) {
            $schedule = $this->allocatePaymentsToSchedule($this->buildLoanPaymentSchedule($loan), $loan->payments, $today);

            foreach ($schedule as $item) {
                if ($item['balance'] <= 0) {
                    continue;
                }
                if ($onlyOverdue && $item['due_date']->gte($today)) {
                    continue;
                }

                if (! $allowFuture && $item['due_date']->gt($today)) {
                    continue;
                }

                $total += $item['balance'];
            }
        }

        return $total;
    }

    private function classifyScheduleStatus(Carbon $dueDate, float $balance, Carbon $today): string
    {
        if ($balance <= 0) {
            return 'Paid';
        }

        if ($dueDate->lt($today)) {
            return 'Installment Overdue';
        }

        if ($dueDate->isSameDay($today)) {
            return 'Due Today';
        }

        return 'Upcoming';
    }

    private function sortStatusCounts(array $counts): array
    {
        return collect($counts)
            ->sortBy(fn ($count, $status) => (
                ($position = array_search(strtolower($status), $this->statusOrder, true)) !== false
                    ? $position
                    : 999
            ))
            ->toArray();
    }

    private function loanTotalPayable(Loan $loan): float
    {
        if (! is_null($loan->total_payable)) {
            return (float) $loan->total_payable;
        }

        if ($loan->principal_amount === null) {
            return 0.0;
        }

        $interestRate = (float) ($loan->interest_rate ?? 0);

        return round((float) $loan->principal_amount * (1 + ($interestRate / 100)), 2);
    }

    private function normalizeFrequency(?string $frequency): string
    {
        if (! $frequency) {
            return 'monthly';
        }

        $normalized = strtolower(trim($frequency));
        $normalized = str_replace([' ', '_'], '-', $normalized);

        return match ($normalized) {
            'daily', 'day', 'd' => 'daily',
            'weekly', 'week', 'w' => 'weekly',
            'semi-monthly', 'semimonthly', 'semi-month', 'semi_monthly' => 'semi-monthly',
            'monthly', 'month', 'm' => 'monthly',
            default => 'monthly',
        };
    }

    private function schedulePeriodCount(int $termMonths, string $frequency): int
    {
        // treat the provided termMonths as the number of installments (terms)
        return max(1, $termMonths);
    }

    private function addFrequency(Carbon $date, int $index, string $frequency): Carbon
    {
        $freq = $this->normalizeFrequency($frequency);

        return match ($freq) {
            'daily' => $date->copy()->addDays($index),
            'weekly' => $date->copy()->addWeeks($index),
            'semi-monthly' => $date->copy()->addDays($index * 15),
            default => $date->copy()->addMonths($index),
        };
    }

    private function monthSelectExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', paid_at) as month",
            'pgsql' => "to_char(paid_at, 'YYYY-MM') as month",
            default => "DATE_FORMAT(paid_at, '%Y-%m') as month",
        };
    }

    private function daySelectExpression(string $column = 'paid_at'): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m-%d', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }

    private function getRecentMonths(int $months, Carbon $today): array
    {
        $monthsList = [];

        for ($index = $months - 1; $index >= 0; $index--) {
            $monthsList[] = $today->copy()->subMonths($index)->format('Y-m');
        }

        return $monthsList;
    }

    public function fillMonthlySeries(array $series, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if (empty($series)) {
            return [];
        }

        $firstMonth = array_key_first($series);
        $lastMonth = array_key_last($series);

        if (! $from && ! $to) {
            $from = Carbon::parse($firstMonth . '-01');
            $to = Carbon::parse($lastMonth . '-01')->endOfMonth();
        }

        if (! $from) {
            $from = Carbon::parse($firstMonth . '-01');
        }

        if (! $to) {
            $to = Carbon::parse($lastMonth . '-01')->endOfMonth();
        }

        $from = $from->copy()->startOfMonth();
        $to = $to->copy()->endOfMonth();

        $filled = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $label = $cursor->format('Y-m');
            $filled[$label] = $series[$label] ?? 0.0;
            $cursor->addMonth();
        }

        return $filled;
    }
}
