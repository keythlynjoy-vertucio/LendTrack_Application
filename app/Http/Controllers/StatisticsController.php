<?php

namespace App\Http\Controllers;

use App\Helpers\PaymentMethodHelper;
use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\LoanMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $metrics = new LoanMetricsService();
        $hasPenaltyColumn = $metrics->hasPenaltyColumn();
        $today = Carbon::today();

        $period = $request->query('period', 'all');
        $from = null;
        $to = null;

        switch ($period) {
            case 'this_month':
                $from = $today->copy()->startOfMonth();
                $to = $today->copy()->endOfMonth();
                break;
            case 'last_month':
                $from = $today->copy()->subMonth()->startOfMonth();
                $to = $today->copy()->subMonth()->endOfMonth();
                break;
            case 'last_3_months':
                $from = $today->copy()->subMonths(2)->startOfMonth();
                $to = $today->copy()->endOfMonth();
                break;
            case 'this_year':
                $from = $today->copy()->startOfYear();
                $to = $today->copy()->endOfYear();
                break;
        }

        $portfolioTotals = $metrics->getPortfolioTotals();
        $totalLoans = array_sum($portfolioTotals['loan_status_count']);
        $totalReleased = $portfolioTotals['released_capital'];
        $totalCollections = $portfolioTotals['total_collections'];
        $totalReceivable = $portfolioTotals['outstanding_balance'];
        $overdueAmount = $portfolioTotals['overdue_amount'];
        $statusCounts = $portfolioTotals['loan_status_count'];

        $trendLabel = match ($period) {
            'this_month' => 'Daily trend for this month',
            'this_year' => 'Monthly trend for this year',
            'all' => 'Long-term trend for all time',
            default => 'Trend for selected period',
        };

        $groupTrendByYear = function (array $series): array {
            $totals = [];
            foreach ($series as $label => $amount) {
                $year = substr($label, 0, 4);
                $totals[$year] = ($totals[$year] ?? 0) + $amount;
            }
            ksort($totals);
            return $totals;
        };

        if ($period === 'this_month') {
            $monthlyCollections = $metrics->getDailyCollections($from, $to);
            $monthlyReleases = $metrics->getDailyReleasesRange($from, $to);

            $dailyCollections = [];
            $dailyReleases = [];
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $key = $cursor->format('Y-m-d');
                $dailyCollections[$key] = $monthlyCollections[$key] ?? 0.0;
                $dailyReleases[$key] = $monthlyReleases[$key] ?? 0.0;
                $cursor->addDay();
            }

            $monthlyCollections = $dailyCollections;
            $monthlyReleases = $dailyReleases;
        } elseif ($period === 'all') {
            $monthlyCollections = $metrics->getMonthlyCollections();
            $monthlyReleases = $metrics->getMonthlyReleases();

            if (max(count($monthlyCollections), count($monthlyReleases)) > 18) {
                $monthlyCollections = $groupTrendByYear($monthlyCollections);
                $monthlyReleases = $groupTrendByYear($monthlyReleases);
            }
        } else {
            $monthlyCollections = $metrics->getMonthlyCollections($from, $to);
            $monthlyReleases = $metrics->getMonthlyReleasesRange($from, $to);

            $monthlyCollections = $metrics->fillMonthlySeries($monthlyCollections, $from, $to);
            $monthlyReleases = $metrics->fillMonthlySeries($monthlyReleases, $from, $to);
        }

        $monthlyCollectionsTotal = array_sum($monthlyCollections);
        $monthlyReleasesTotal = array_sum($monthlyReleases);

        // Cash flow compare monthlyCollections (inflows) vs monthlyReleases (outflows)
        $allMonths = array_values(array_unique(array_merge(array_keys($monthlyCollections), array_keys($monthlyReleases))));
        sort($allMonths);
        $inflows = array_map(fn($m) => $monthlyCollections[$m] ?? 0, $allMonths);
        $outflows = array_map(fn($m) => $monthlyReleases[$m] ?? 0, $allMonths);

        $dataAsOf = Carbon::now()->format('F j, Y g:i A');
        $totalPayable = $metrics->getTotalPayable();
        $interestAmount = max(0.0, $totalPayable - $totalReleased);
        $moneyOverviewLabels = ['Principal', 'Interest', 'Payable', 'Paid', 'Balance', 'Cash'];
        $moneyOverviewValues = [
            $totalReleased,
            $interestAmount,
            $totalPayable,
            $totalCollections,
            $totalReceivable,
            $totalCollections,
        ];

        $collectionRate = $totalPayable > 0 ? round(($totalCollections / $totalPayable) * 100, 1) : 0.0;
        $activeLoanCount = Loan::whereIn('status', ['active', 'overdue'])->count();
        $overdueLoanCount = Loan::where('status', 'overdue')->count();
        $overdueRate = $activeLoanCount > 0 ? round(($overdueLoanCount / $activeLoanCount) * 100, 1) : 0.0;
        $avgLoanSize = $totalLoans > 0 ? round($totalReleased / $totalLoans, 2) : 0.0;
        $paymentCount = Payment::count();
        $avgPaymentSize = $paymentCount > 0 ? round($totalCollections / $paymentCount, 2) : 0.0;

        $topBorrowers = $metrics->getTopBorrowersByOutstanding();

        $topPayingBorrowers = Payment::query()
            ->join('loans', 'payments.loan_id', '=', 'loans.id')
            ->join('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
            ->whereNull('borrowers.deleted_at')
            ->whereNotIn('loans.status', ['pending', 'cancelled', 'closed'])
            ->selectRaw("CONCAT(TRIM(borrowers.last_name), ', ', TRIM(borrowers.first_name)) as borrower")
            ->selectRaw('SUM(amount' . ($hasPenaltyColumn ? ' + COALESCE(penalty_paid, 0)' : '') . ') as total')
            ->groupBy('borrower')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn($r) => ['borrower' => $r->borrower, 'total' => (float) $r->total])
            ->toArray();

        $paymentMethods = [];
        if (Schema::hasColumn('payments', 'method')) {
            $paymentQuery = Payment::query()
                ->when($from, fn ($query) => $query->whereDate('paid_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('paid_at', '<=', $to));

            $paymentMethods = $paymentQuery
                ->selectRaw("UPPER(COALESCE(method, 'OTHER')) as raw_method")
                ->selectRaw('SUM(amount' . ($hasPenaltyColumn ? ' + COALESCE(penalty_paid, 0)' : '') . ') as total')
                ->groupBy('raw_method')
                ->orderByDesc('total')
                ->get()
                ->map(fn($r) => [
                    'method' => PaymentMethodHelper::getLabel($r->raw_method),
                    'total' => (float) $r->total
                ])
                ->toArray();
        }

        $borrowerStatusCounts = Borrower::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $frequencyBuckets = [
            'Daily' => 0,
            'Weekly' => 0,
            'Semi-Monthly' => 0,
            'Monthly' => 0,
            'Other' => 0,
        ];

        $loanFrequencyQuery = Loan::query()
            ->whereNotIn('status', ['pending', 'cancelled', 'closed'])
            ->when($from, fn ($query) => $query->whereDate('start_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('start_date', '<=', $to))
            ->selectRaw("CASE
                WHEN LOWER(COALESCE(frequency, '')) LIKE '%daily%' THEN 'Daily'
                WHEN LOWER(COALESCE(frequency, '')) LIKE '%week%' THEN 'Weekly'
                WHEN LOWER(COALESCE(frequency, '')) LIKE '%semi%' OR LOWER(COALESCE(frequency, '')) LIKE '%semi-month%' THEN 'Semi-Monthly'
                WHEN LOWER(COALESCE(frequency, '')) LIKE '%month%' THEN 'Monthly'
                ELSE 'Other'
            END as normalized_frequency")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('normalized_frequency')
            ->orderByRaw("FIELD(normalized_frequency, 'Daily', 'Weekly', 'Semi-Monthly', 'Monthly', 'Other')");

        $frequencyRows = $loanFrequencyQuery->get();

        foreach ($frequencyRows as $row) {
            $frequencyBuckets[$row->normalized_frequency] = (int) $row->total;
        }

        $paymentSizeBuckets = [
            '₱1–₱500' => 0,
            '₱501–₱1,000' => 0,
            '₱1,001–₱5,000' => 0,
            '₱5,001–₱10,000' => 0,
            '₱10,001+' => 0,
        ];

        $paymentSizeQuery = Payment::query()
            ->when($from, fn ($query) => $query->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('paid_at', '<=', $to));

        $paymentSizeRows = $paymentSizeQuery
            ->selectRaw("CASE
                WHEN (amount" . ($hasPenaltyColumn ? ' + COALESCE(penalty_paid, 0)' : '') . ") <= 500 THEN '₱1–₱500'
                WHEN (amount" . ($hasPenaltyColumn ? ' + COALESCE(penalty_paid, 0)' : '') . ") <= 1000 THEN '₱501–₱1,000'
                WHEN (amount" . ($hasPenaltyColumn ? ' + COALESCE(penalty_paid, 0)' : '') . ") <= 5000 THEN '₱1,001–₱5,000'
                WHEN (amount" . ($hasPenaltyColumn ? ' + COALESCE(penalty_paid, 0)' : '') . ") <= 10000 THEN '₱5,001–₱10,000'
                ELSE '₱10,001+'
            END as bucket")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('bucket')
            ->get();

        foreach ($paymentSizeRows as $row) {
            $paymentSizeBuckets[$row->bucket] = (int) $row->count;
        }

        $cutoffBuckets = $metrics->getCutoffBuckets();
        $upcoming = $metrics->getUpcomingSummary(
            10,
            $today,
            $from ?? $today->copy()->startOfMonth(),
            $to ?? $today->copy()->endOfMonth()
        );

        $upcomingItems = $upcoming['items'];
        $upcomingSummary = $upcoming['summary'];
        $cutoffDate = isset($upcomingItems[0]) ? Carbon::parse($upcomingItems[0]['due_date']) : null;
        $cutoffLabel = null;

        if ($cutoffDate) {
            $cutoffLabel = $cutoffDate->lte($today) ? 'Current cutoff' : 'Next cutoff';
        }

        return view('statistics.index', compact(
            'totalLoans',
            'totalReleased',
            'totalCollections',
            'totalReceivable',
            'overdueAmount',
            'statusCounts',
            'monthlyCollections',
            'monthlyReleases',
            'allMonths',
            'inflows',
            'outflows',
            'moneyOverviewLabels',
            'moneyOverviewValues',
            'collectionRate',
            'overdueRate',
            'avgLoanSize',
            'avgPaymentSize',
            'topBorrowers',
            'topPayingBorrowers',
            'paymentMethods',
            'frequencyBuckets',
            'paymentSizeBuckets',
            'borrowerStatusCounts',
            'cutoffBuckets',
            'upcomingItems',
            'upcomingSummary',
            'cutoffDate',
            'cutoffLabel',
            'period',
            'trendLabel',
            'monthlyCollectionsTotal',
            'monthlyReleasesTotal',
            'dataAsOf'
        ));
    }
}
