<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Payment Report</title>
        <style>
            body { font-family: sans-serif; color: #111827; }
            table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
            th, td { border: 1px solid #d1d5db; padding: 0.5rem; }
            th { background: #f3f4f6; text-align: left; }
        </style>
    </head>
    <body>
        <h1>Payment Report</h1>
        <p>From: {{ $from ?? 'Any' }} - To: {{ $to ?? 'Any' }}</p>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Loan Reference</th>
                    <th>Amount</th>
                    <th>Penalty Paid</th>
                    <th>Total Collected</th>
                    <th>Payment Date</th>
                    <th>Method</th>
                    <th>Reference Number</th>
                    <th>User</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $payment->id }}</td>
                        <td>{{ $payment->loan->reference_no }}</td>
                        <td>@currency($payment->amount)</td>
                        <td>@currency($payment->penalty_paid)</td>
                        <td>@currency($payment->amount + ($payment->penalty_paid ?? 0))</td>
                        <td>@dateYmd($payment->paid_at)</td>
                        <td>{{ ucfirst($payment->method) }}</td>
                        <td>{{ $payment->reference_no }}</td>
                        <td>{{ $payment->user?->name ?? 'System' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </body>
</html>
