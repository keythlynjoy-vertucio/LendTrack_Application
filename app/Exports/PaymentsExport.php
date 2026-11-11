<?php

namespace App\Exports;

use App\Helpers\PaymentMethodHelper;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PaymentsExport implements FromCollection, WithHeadings
{
    public function __construct(protected $payments)
    {
    }

    public function collection(): Collection
    {
        return collect($this->payments->map(function ($payment) {
            return [
                $payment->id,
                $payment->loan->reference_no,
                number_format((float) $payment->amount, 2, '.', ''),
                number_format((float) $payment->penalty_paid, 2, '.', ''),
                number_format((float) ($payment->amount + $payment->penalty_paid), 2, '.', ''),
                $payment->paid_at?->format('Y-m-d'),
                PaymentMethodHelper::getLabel($payment->method),
                $payment->reference_no,
                $payment->user?->name,
            ];
        }));
    }

    public function headings(): array
    {
        return [
            'ID',
            'Loan Reference',
            'Amount',
            'Penalty Paid',
            'Total Collected',
            'Payment Date',
            'Method',
            'Reference Number',
            'Recorded By',
        ];
    }
}
