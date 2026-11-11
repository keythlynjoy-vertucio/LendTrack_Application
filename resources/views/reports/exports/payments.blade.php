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
            <th>Recorded By</th>
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
