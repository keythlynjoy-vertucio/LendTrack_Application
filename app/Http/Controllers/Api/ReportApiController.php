<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class ReportApiController extends Controller
{
    public function collections(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $toRules = ['nullable', 'date_format:Y-m-d'];

        if ($request->filled('from')) {
            $toRules[] = 'after_or_equal:from';
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => $toRules,
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        $query = Payment::query();

        if ($from !== null) {
            $query->whereDate('paid_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('paid_at', '<=', $to);
        }

        $totals = $query
            ->selectRaw('COUNT(*) AS payment_count')
            ->selectRaw('COUNT(DISTINCT loan_id) AS loans_count')
            ->selectRaw(
                'COALESCE(SUM(amount), 0) AS regular_collected'
            )
            ->selectRaw(
                'COALESCE(SUM(penalty_paid), 0) AS penalty_collected'
            )
            ->first();

        $regular = round((float) $totals->regular_collected, 2);
        $penalty = round((float) $totals->penalty_collected, 2);

        return response()->json([
            'from' => $from,
            'to' => $to,
            'count' => (int) $totals->payment_count,
            'loans_count' => (int) $totals->loans_count,
            'regular_collected' => $regular,
            'penalty_collected' => $penalty,
            'total_collected' => round($regular + $penalty, 2),
        ]);
    }
}