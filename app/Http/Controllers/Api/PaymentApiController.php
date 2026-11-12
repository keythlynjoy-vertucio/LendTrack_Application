<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentRecorded;
use App\Services\LoanMetricsService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentApiController extends Controller
{
    public function store(Request $request, Loan $loan)
    {
        abort_unless(
            $request->user()?->isAdmin()
                || $request->user()?->hasPermission('record_payments'),
            403
        );

        $validated = $request->validate([
            'client_request_id' => [
                'required',
                'uuid',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
                'regex:/^[0-9]+(?:\.[0-9]{1,2})?$/',
            ],
            'penalty_paid' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
                'regex:/^[0-9]+(?:\.[0-9]{1,2})?$/',
            ],
            'paid_at' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'method' => [
                'required',
                Rule::in(['cash', 'gcash', 'bank', 'manual']),
            ],
            'reference_no' => [
                'nullable',
                'string',
                'max:255',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $requestId = strtolower($validated['client_request_id']);

        // Normalize the payload so equivalent retries have the same hash.
        $data = [
            'loan_id' => (int) $loan->id,
            'user_id' => (int) $request->user()->id,
            'amount' => number_format(
                (float) $validated['amount'],
                2,
                '.',
                ''
            ),
            'penalty_paid' => number_format(
                (float) ($validated['penalty_paid'] ?? 0),
                2,
                '.',
                ''
            ),
            'paid_at' => $validated['paid_at'],
            'method' => $validated['method'],
            'reference_no' => $this->nullableText(
                $validated['reference_no'] ?? null
            ),
            'remarks' => $this->nullableText(
                $validated['remarks'] ?? null
            ),
        ];

        $hash = hash(
            'sha256',
            json_encode($data, JSON_THROW_ON_ERROR)
        );

        try {
            $result = DB::transaction(function () use (
                $loan,
                $data,
                $requestId,
                $hash
            ) {
                $lockedLoan = Loan::query()
                    ->whereKey($loan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = Payment::withTrashed()
                    ->where('client_request_id', $requestId)
                    ->first();

                // Check retries before checking the loan's current status.
                if ($existing) {
                    $this->assertSameRequest($existing, $hash);

                    return [
                        'payment' => $existing,
                        'created' => false,
                    ];
                }

                if (! $lockedLoan->borrower()->whereNull('borrowers.deleted_at')->exists()) {
                    throw ValidationException::withMessages([
                        'loan_id' => [
                            'The borrower is no longer available.',
                        ],
                    ]);
                }

                $status = strtolower(trim((string) $lockedLoan->status));

                if (! in_array($status, ['active', 'overdue', 'paid'], true)) {
                    throw ValidationException::withMessages([
                        'loan_id' => [
                            'Payments can only be recorded for active or overdue loans.',
                        ],
                    ]);
                }

                $metrics = new LoanMetricsService();
                $totals = $metrics->getLoanPaymentTotals($lockedLoan);

                $payableCents = (int) round(
                    (float) $lockedLoan->total_payable * 100
                );

                $paidCents = (int) round($totals['regular'] * 100);

                $balanceCents = max(0, $payableCents - $paidCents);
                $amountCents = (int) round((float) $data['amount'] * 100);

                $quote = (new \App\Services\CalendarLoanService())->quote($lockedLoan, $data['paid_at']);
                $penaltyCents = (int) round((float) $data['penalty_paid'] * 100);
                if ($amountCents + $penaltyCents <= 0) {
                    throw ValidationException::withMessages(['amount' => 'Enter a regular or penalty payment greater than zero.']);
                }
                if ($lockedLoan->interest_months && $penaltyCents > (int) round($quote['penalty_outstanding'] * 100)) {
                    throw ValidationException::withMessages(['penalty_paid' => 'Penalty exceeds the unpaid penalty for the selected date. Refresh the suggested amounts.']);
                }

                if ($amountCents > $balanceCents) {
                    throw ValidationException::withMessages([
                        'amount' => [
                            'Amount exceeds the remaining regular balance of '
                                . number_format($balanceCents / 100, 2)
                                . '. Enter penalties separately.',
                        ],
                    ]);
                }

                $payment = new Payment();
                $payment->fill($data);

                // Assigned directly; no Payment model changes are needed.
                $payment->client_request_id = $requestId;
                $payment->client_request_hash = $hash;
                $payment->save();

                // Ensure totals include the newly saved payment.
                $lockedLoan->unsetRelation('payments');
                $metrics->syncLoanTotals($lockedLoan);

                $after = (new \App\Services\CalendarLoanService())->quote($lockedLoan, $data['paid_at']);
                $payment->receipt_snapshot = [
                    'regular_balance' => $after['regular_outstanding'],
                    'penalty_balance' => $after['penalty_outstanding'],
                    'borrower' => $lockedLoan->borrower?->full_name,
                    'received_by' => User::find($data['user_id'])?->name,
                    'loan_reference' => $lockedLoan->reference_no,
                ];
                $payment->save();

                ActivityLog::create([
                    'user_id' => $data['user_id'],
                    'subject_type' => Payment::class,
                    'subject_id' => $payment->id,
                    'action' => 'created',
                    'description' => 'Recorded payment for loan '
                        . $lockedLoan->reference_no,
                    'changes' => array_merge($data, [
                        'client_request_id' => $requestId,
                    ]),
                ]);

                return [
                    'payment' => $payment,
                    'created' => true,
                ];
            });
        } catch (QueryException $exception) {
            // The unique index also protects against a simultaneous retry.
            $existing = Payment::withTrashed()
                ->where('client_request_id', $requestId)
                ->first();

            if (! $existing) {
                throw $exception;
            }

            $this->assertSameRequest($existing, $hash);

            $result = [
                'payment' => $existing,
                'created' => false,
            ];
        }

        $payment = $result['payment'];

        // Notify only for a new payment, after the transaction commits.
        // A notification error must not make a saved payment appear failed.
        if ($result['created']) {
            try {
                Notification::send(
                    User::query()->where('role', 'admin')->get(),
                    new PaymentRecorded($payment)
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $currentLoan = Loan::query()->findOrFail($loan->id);
        $totals = (new LoanMetricsService())
            ->getLoanPaymentTotals($currentLoan);

        return response()->json([
            'message' => $result['created']
                ? 'Payment recorded successfully.'
                : 'This payment was already recorded.',
            'replayed' => ! $result['created'],
            'data' => [
                'id' => $payment->id,
                'loan_id' => $payment->loan_id,
                'amount' => $payment->amount,
                'penalty_paid' => $payment->penalty_paid,
                'paid_at' => $payment->paid_at?->format('Y-m-d'),
                'method' => $payment->method,
                'reference_no' => $payment->reference_no,
                'remarks' => $payment->remarks,
                'total_received' => round(
                    (float) $payment->amount
                        + (float) $payment->penalty_paid,
                    2
                ),
            ],
            'loan' => [
                'id' => $currentLoan->id,
                'reference_no' => $currentLoan->reference_no,
                'total_payable' => $currentLoan->total_payable,
                'total_paid' => $totals['regular'],
                'remaining_regular_balance' => round(max(
                    0,
                    (float) $currentLoan->total_payable
                        - $totals['regular']
                ), 2),
                'status' => $currentLoan->status,
            ],
        ], $result['created'] ? 201 : 200);
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim($value ?? '');

        return $value === '' ? null : $value;
    }

    private function assertSameRequest(
        Payment $payment,
        string $hash
    ): void {
        abort_if(
            $payment->trashed(),
            409,
            'This submission belongs to a deleted payment. Refresh the loan before continuing.'
        );

        abort_unless(
            is_string($payment->client_request_hash)
                && hash_equals($payment->client_request_hash, $hash),
            409,
            'This request ID was already used for a different payment. Refresh before continuing.'
        );
    }
}