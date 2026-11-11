<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LendTrack Payment Report - @dateYmd(now())</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #ffffff;
            color: #000;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            font-size: 28px;
            margin-bottom: 10px;
            color: #1e293b;
        }
        .report-info {
            text-align: center;
            font-size: 14px;
            color: #64748b;
            margin-bottom: 30px;
        }
        .report-period {
            text-align: center;
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f1f5f9;
            border-radius: 8px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        thead {
            background-color: #1e293b;
            color: white;
        }
        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #cbd5e1;
        }
        td {
            padding: 12px;
            border: 1px solid #e2e8f0;
        }
        tbody tr:nth-child(odd) {
            background-color: #f8fafc;
        }
        tbody tr:hover {
            background-color: #f1f5f9;
        }
        .text-right {
            text-align: right;
        }
        .currency {
            font-family: "Courier New", monospace;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background-color: #f1f5f9;
            border-radius: 8px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .summary-item {
            text-align: center;
        }
        .summary-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 12px;
            color: #64748b;
        }
        .empty-message {
            text-align: center;
            padding: 40px;
            color: #64748b;
            font-size: 14px;
        }
        @media print {
            body {
                margin: 0;
                padding: 10mm;
            }
            .container {
                max-width: 100%;
            }
            table {
                page-break-inside: avoid;
            }
            thead {
                display: table-header-group;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Payment Report</h1>
        <div class="report-info">
            LendTrack Payment Collection Report<br>
            Generated on @dateYmd(now())
        </div>

        @if ($from || $to)
            <div class="report-period">
                <strong>Period:</strong>
                @if ($from)
                    @dateYmd(\Carbon\Carbon::parse($from))
                @else
                    Beginning
                @endif
                to
                @if ($to)
                    @dateYmd(\Carbon\Carbon::parse($to))
                @else
                    Today
                @endif
            </div>
        @endif

        @if ($payments->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Loan Reference</th>
                        <th>Borrower</th>
                        <th class="text-right">Amount</th>
                        <th class="text-right">Penalty</th>
                        <th class="text-right">Total</th>
                        <th>Method</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalAmount = 0;
                        $totalPenalty = 0;
                    @endphp
                    @foreach ($payments as $payment)
                        @php
                            $totalAmount += (float) $payment->amount;
                            $totalPenalty += (float) ($payment->penalty_paid ?? 0);
                        @endphp
                        <tr>
                            <td>@dateYmd($payment->paid_at)</td>
                            <td>{{ $payment->loan->reference_no }}</td>
                            <td>{{ $payment->loan->borrower->last_name }}, {{ $payment->loan->borrower->first_name }}</td>
                            <td class="text-right currency">@currency($payment->amount)</td>
                            <td class="text-right currency">@currency($payment->penalty_paid ?? 0)</td>
                            <td class="text-right currency">@currency($payment->amount + ($payment->penalty_paid ?? 0))</td>
                            <td>{{ \App\Helpers\PaymentMethodHelper::getLabel($payment->method) }}</td>
                            <td>{{ $payment->user?->name ?? 'System' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="summary">
                <div class="summary-item">
                    <div class="summary-label">Total Regular Payments</div>
                    <div class="summary-value currency">@currency($totalAmount)</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Penalty Paid</div>
                    <div class="summary-value currency">@currency($totalPenalty)</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Grand Total</div>
                    <div class="summary-value currency">@currency($totalAmount + $totalPenalty)</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Records</div>
                    <div class="summary-value">{{ $payments->count() }}</div>
                </div>
            </div>
        @else
            <div class="empty-message">
                No payment records found for the selected period.
            </div>
        @endif

        <div class="footer">
            <p>This report is confidential and intended for authorized users only.</p>
            <p>To save as PDF, use your browser's Print function (Ctrl+P or Cmd+P) and select "Save as PDF".</p>
        </div>
    </div>
</body>
</html>
