<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\LoanMetricsService;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $weekEnd = $today->copy()->addDays(7);

        $metrics = new LoanMetricsService();
        $portfolioTotals = $metrics->getPortfolioTotals($today);

        $borrowersCount = Borrower::count();
        $activeLoansCount = Loan::whereRaw('LOWER(status) IN (?, ?)', ['active', 'overdue'])->count();
        $overdueLoansCount = Loan::whereRaw('LOWER(status) = ?', ['overdue'])->count();

        $totalReleased = $portfolioTotals['released_capital'];
        $collectionBreakdown = [
            'regular' => $portfolioTotals['regular_payments'],
            'penalty' => $portfolioTotals['penalty_payments'],
            'total' => $portfolioTotals['total_collections'],
        ];
        $totalCollected = $portfolioTotals['total_collections'];
        $collectionsThisMonth = $metrics->getCollectionsTotals($monthStart, $monthEnd)['total'];

        $statusCounts = $portfolioTotals['loan_status_count'];

        $upcomingData = $metrics->getUpcomingSummary(5, $today, $monthStart, $monthEnd);
        $upcomingCollections = collect($upcomingData['items'])
            ->map(fn ($item) => array_merge($item, ['action_url' => route('loans.show', $item['loan_id'])]))
            ->all();
        $upcomingSummary = $upcomingData['summary'];

        $upcomingCardMetrics = [
            'overdue' => $portfolioTotals['overdue_amount'],
            'due_today' => $portfolioTotals['due_today'],
            'due_this_week' => $portfolioTotals['due_this_week'],
            'upcoming' => $portfolioTotals['upcoming_amount'],
        ];

        $totalOutstanding = (float) $portfolioTotals['outstanding_balance'];
        $overdueBalance = (float) $portfolioTotals['overdue_amount'];
        $dueThisWeek = (float) $portfolioTotals['due_this_week'];
        $dueThisWeekCount = (int) $portfolioTotals['due_this_week_count'];

        $monthlyCollections = $metrics->getRecentMonthlyCollections(6, $today);

        $recentPayments = Payment::query()->with('loan.borrower')->latest('paid_at')->limit(5)->get();

        return view('dashboard', compact(
            'borrowersCount',
            'activeLoansCount',
            'overdueLoansCount',
            'totalReleased',
            'totalCollected',
            'collectionsThisMonth',
            'totalOutstanding',
            'overdueBalance',
            'dueThisWeek',
            'dueThisWeekCount',
            'recentPayments',
            'statusCounts',
            'monthlyCollections',
            'upcomingCollections',
            'upcomingSummary',
            'upcomingCardMetrics',
            'collectionBreakdown',
        ));
    }

}
