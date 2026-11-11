<?php

namespace App\Http\Controllers;

use App\Exports\PaymentsExport;
use App\Helpers\PaymentMethodHelper;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Services\LoanMetricsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $metrics = new LoanMetricsService();
        $hasPenaltyColumn = $metrics->hasPenaltyColumn();

        $paymentQuery = Payment::query()
            ->with(['loan.borrower', 'user'])
            ->when($from, fn ($query) => $query->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('paid_at', '<=', $to));

        $payments = (clone $paymentQuery)
            ->latest('paid_at')
            ->paginate(10)
            ->withQueryString();

        $statusCounts = $metrics->getLoanStatusCounts($from ? Carbon::parse($from) : null, $to ? Carbon::parse($to) : null);

        $monthlyCollections = $metrics->getMonthlyCollections($from ? Carbon::parse($from) : null, $to ? Carbon::parse($to) : null);
        $monthlyCollections = $metrics->fillMonthlySeries($monthlyCollections, $from ? Carbon::parse($from) : null, $to ? Carbon::parse($to) : null);

        // Removed detailed insights computations (paymentMethods, regularVsPenalty, overdueByBorrower)
        // to avoid unnecessary work for the simplified Reports UI.
        $loanStatuses = count($statusCounts);
        $paymentRecords = $payments->total();

        return view('reports.index', compact(
            'payments',
            'from',
            'to',
            'statusCounts',
            'monthlyCollections',
            'loanStatuses',
            'paymentRecords',
            'hasPenaltyColumn'
        ));
    }

    public function export(Request $request)
    {
        $format = $request->query('format', 'csv');
        $from = $request->query('from');
        $to = $request->query('to');

        $payments = Payment::query()
            ->with('loan.borrower')
            ->when($from, fn ($query) => $query->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('paid_at', '<=', $to))
            ->latest('paid_at')
            ->get();

        if ($format === 'pdf') {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'subject_type' => Payment::class,
                'subject_id' => null,
                'action' => 'exported_pdf',
                'description' => 'Exported payment report as PDF',
                'changes' => ['from' => $from, 'to' => $to],
            ]);

            return $this->generatePrintablePDF($payments, $from, $to);
        }

        if ($format === 'excel') {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'subject_type' => Payment::class,
                'subject_id' => null,
                'action' => 'exported_excel',
                'description' => 'Exported payment report as Excel',
                'changes' => ['from' => $from, 'to' => $to],
            ]);

            return $this->generateExcelXLS($payments);
        }

        // CSV export (default)
        ActivityLog::create([
            'user_id' => auth()->id(),
            'subject_type' => Payment::class,
            'subject_id' => null,
            'action' => 'exported_csv',
            'description' => 'Exported payment report as CSV',
            'changes' => ['from' => $from, 'to' => $to],
        ]);

        return $this->generateCSV($payments);
    }

    private function generateCSV($payments)
    {
        $csv = collect($payments)->map(function ($payment) {
            $paidAt = $payment->paid_at;
            return [
                'ID' => $payment->id,
                'Loan Reference' => $payment->loan->reference_no,
                'Amount' => number_format((float) $payment->amount, 2, '.', ''),
                'Penalty Paid' => number_format((float) $payment->penalty_paid, 2, '.', ''),
                'Total Collected' => number_format((float) ($payment->amount + $payment->penalty_paid), 2, '.', ''),
                'Payment Date' => $paidAt instanceof \DateTimeInterface ? $paidAt->format('Y-m-d') : null,
                'Method' => PaymentMethodHelper::getLabel($payment->method),
                'Reference Number' => $payment->reference_no,
                'Recorded By' => $payment->user?->name,
            ];
        });

        $filename = 'lendtrack-payments-report-'.now()->format('Ymd').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($csv) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_keys($csv->first() ?? []));
            foreach ($csv as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }

    private function generateExcelXLS($payments)
    {
        $csv = collect($payments)->map(function ($payment) {
            $paidAt = $payment->paid_at;
            return [
                'ID' => $payment->id,
                'Loan Reference' => $payment->loan->reference_no,
                'Amount' => (float) $payment->amount,
                'Penalty Paid' => (float) $payment->penalty_paid,
                'Total Collected' => (float) ($payment->amount + $payment->penalty_paid),
                'Payment Date' => $paidAt instanceof \DateTimeInterface ? $paidAt->format('Y-m-d') : null,
                'Method' => PaymentMethodHelper::getLabel($payment->method),
                'Reference Number' => $payment->reference_no,
                'Recorded By' => $payment->user?->name,
            ];
        });

        $filename = 'lendtrack-payments-report-'.now()->format('Ymd').'.csv';

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($csv) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, array_keys($csv->first() ?? []));
            foreach ($csv as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }

    private function generatePrintablePDF($payments, $from, $to)
    {
        $html = view('reports.exports.html', [
            'payments' => $payments,
            'from' => $from,
            'to' => $to,
        ])->render();

        $filename = 'lendtrack-payments-report-'.now()->format('Ymd').'.pdf';

        $headers = [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => "inline; filename={$filename}",
        ];

        return Response::make($html, 200, $headers);
    }
}
