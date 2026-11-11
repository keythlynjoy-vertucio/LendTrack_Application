<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\LoanMetricsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CollectionApiController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()?->isAdmin()
                || $request->user()?->isStaff(),
            403
        );

        $validated = $request->validate([
            'bucket' => [
                'nullable',
                Rule::in([
                    'all',
                    'overdue',
                    'due_today',
                    'upcoming',
                ]),
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $bucket = $validated['bucket'] ?? 'all';
        $search = trim($validated['search'] ?? '');
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);

        $today = Carbon::today();
        $todayText = $today->toDateString();
        $metrics = new LoanMetricsService();

        $items = [];

        $summary = [
            'overdue' => ['count' => 0, 'balance' => 0.0],
            'due_today' => ['count' => 0, 'balance' => 0.0],
            'upcoming' => ['count' => 0, 'balance' => 0.0],
            'total' => ['count' => 0, 'balance' => 0.0],
        ];

        Loan::query()
            ->with(['borrower', 'payments'])
            ->whereHas('borrower', function ($query) {
                $query->whereNull('deleted_at');
            })
            ->whereIn(
                DB::raw('LOWER(TRIM(status))'),
                ['active', 'overdue']
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where(
                        'reference_no',
                        'like',
                        "%{$search}%"
                    )->orWhereHas('borrower', function ($query) use ($search) {
                        $query->where(
                            'first_name',
                            'like',
                            "%{$search}%"
                        )->orWhere(
                            'last_name',
                            'like',
                            "%{$search}%"
                        )->orWhere(
                            'phone',
                            'like',
                            "%{$search}%"
                        );
                    });
                });
            })
            ->chunkById(100, function ($loans) use (
                $metrics,
                $today,
                $todayText,
                &$items,
                &$summary
            ) {
                foreach ($loans as $loan) {
                    $borrower = $loan->borrower;

                    if (! $borrower) {
                        continue;
                    }

                    $schedule = $metrics->collectionSchedule(
                        $loan,
                        $today
                    );

                    foreach ($schedule as $index => $installment) {
                        $balance = round(
                            (float) $installment['balance'],
                            2
                        );

                        if ($balance <= 0) {
                            continue;
                        }

                        $dueDate = $installment['due_date'];

                        if ($dueDate < $todayText) {
                            $itemBucket = 'overdue';
                        } elseif ($dueDate === $todayText) {
                            $itemBucket = 'due_today';
                        } else {
                            $itemBucket = 'upcoming';
                        }

                        $summary[$itemBucket]['count']++;
                        $summary[$itemBucket]['balance'] += $balance;
                        $summary['total']['count']++;
                        $summary['total']['balance'] += $balance;

                        $items[] = [
                            'loan_id' => $loan->id,
                            'reference_no' => $loan->reference_no,
                            'borrower_id' => $borrower->id,
                            'borrower_name' => trim(
                                $borrower->first_name
                                    . ' '
                                    . $borrower->last_name
                            ),
                            'phone' => $borrower->phone,
                            'installment_no' => $index + 1,
                            'due_date' => $dueDate,
                            'expected_amount' => round(
                                (float) $installment['expected'],
                                2
                            ),
                            'amount_paid' => round(
                                (float) $installment['actual_paid'],
                                2
                            ),
                            'remaining_amount' => $balance,
                            'bucket' => $itemBucket,
                        ];
                    }
                }
            });

        foreach ($summary as $key => $values) {
            $summary[$key]['balance'] = round(
                $values['balance'],
                2
            );
        }

        if ($bucket !== 'all') {
            $items = array_values(array_filter(
                $items,
                fn ($item) => $item['bucket'] === $bucket
            ));
        }

        usort(
            $items,
            fn ($left, $right) => [
                $left['due_date'],
                $left['loan_id'],
                $left['installment_no'],
            ] <=> [
                $right['due_date'],
                $right['loan_id'],
                $right['installment_no'],
            ]
        );

        $paginator = new LengthAwarePaginator(
            array_slice($items, ($page - 1) * $perPage, $perPage),
            count($items),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json(array_merge(
            $paginator->toArray(),
            [
                'as_of' => $todayText,
                'bucket' => $bucket,
                'summary' => $summary,
            ]
        ));
    }
}