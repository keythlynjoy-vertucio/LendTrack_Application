<?php

namespace App\Http\Controllers;

use App\Models\Borrower;
use App\Models\Loan;
use App\Services\LoanMetricsService;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class DiscrepancyReportController extends Controller
{
    public function index(Request $request)
    {
        $borrowers = Borrower::orderBy('last_name')->get();

        $borrowerId = $request->query('borrower_id');
        $loanId = $request->query('loan_id');
        $asOfParam = $request->query('as_of') ?? $request->query('as_of_date');
        $asOf = $asOfParam ? Carbon::parse($asOfParam) : Carbon::today();

        $selectedBorrower = $borrowerId ? Borrower::find($borrowerId) : null;
        $loans = $selectedBorrower ? $selectedBorrower->loans()->whereNotIn('status', ['closed', 'cancelled', 'written_off', 'paid'])->orderBy('start_date')->get() : collect();
        $selectedLoan = $loanId ? Loan::with(['borrower', 'payments'])->find($loanId) : null;

        if (! $selectedBorrower && $selectedLoan && $selectedLoan->borrower) {
            $selectedBorrower = $selectedLoan->borrower;
        }

        $summary = null;
        $rows = [];
        $message = null;

        if ($selectedLoan) {
            if ($selectedBorrower && $selectedLoan->borrower_id !== $selectedBorrower->id) {
                return back()->withErrors(['loan' => 'Selected loan does not belong to selected borrower.']);
            }

            $metrics = new LoanMetricsService();
            $discrepancyData = $metrics->buildDiscrepancySchedule($selectedLoan, $asOf);
            $summary = $discrepancyData['summary'];
            $rows = $discrepancyData['rows'];

            $borrowerName = $selectedLoan->borrower ? ($selectedLoan->borrower->last_name . ', ' . $selectedLoan->borrower->first_name) : ($selectedBorrower ? ($selectedBorrower->last_name . ', ' . $selectedBorrower->first_name) : 'Unknown borrower');
            $loanRef = $selectedLoan->reference_no;
            $asOfFormatted = $asOf->format('M d, Y');

            if ($discrepancyData['discrepancy'] > 0) {
                $message = "{$borrowerName}, {$loanRef}: Expected paid by {$asOfFormatted} is ₱" . number_format($discrepancyData['expected_paid_by'], 2) . ", actual paid is ₱" . number_format($discrepancyData['actual_paid_by'], 2) . ". Current overdue as of {$asOfFormatted} is ₱" . number_format($discrepancyData['discrepancy'], 2) . ".";
            } elseif ($discrepancyData['advance'] > 0) {
                $message = "{$borrowerName}, {$loanRef}: Expected paid by {$asOfFormatted} is ₱" . number_format($discrepancyData['expected_paid_by'], 2) . ", actual paid is ₱" . number_format($discrepancyData['actual_paid_by'], 2) . ". No current overdue as of {$asOfFormatted}. Advance payment is ₱" . number_format($discrepancyData['advance'], 2) . ".";
            } else {
                $message = "{$borrowerName}, {$loanRef}: Expected paid by {$asOfFormatted} is ₱" . number_format($discrepancyData['expected_paid_by'], 2) . ", actual paid is ₱" . number_format($discrepancyData['actual_paid_by'], 2) . ". The loan is on track as of {$asOfFormatted}.";
            }
        }

        return view('reports.discrepancy', compact('borrowers', 'loans', 'selectedBorrower', 'selectedLoan', 'asOf', 'summary', 'rows', 'message'));
    }

    public function export(Request $request)
    {
        $loanId = $request->query('loan_id');
        $asOfParam = $request->query('as_of') ?? $request->query('as_of_date');
        $asOf = $asOfParam ? Carbon::parse($asOfParam) : Carbon::today();

        if (! $loanId) {
            return back()->withErrors(['loan' => 'Please select a loan to export.']);
        }

        $selectedLoan = Loan::find($loanId);
        if (! $selectedLoan) {
            return back()->withErrors(['loan' => 'Loan not found.']);
        }

        $metrics = new LoanMetricsService();
        $discrepancyData = $metrics->buildDiscrepancySchedule($selectedLoan, $asOf);
        $rows = $discrepancyData['rows'];

        $filename = 'discrepancy_' . $selectedLoan->reference_no . '_' . now()->format('Ymd') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Due Date', 'Included in Computation', 'Actual Payment Date', 'Expected Due', 'Actual Payment', 'Difference', 'Running Difference', 'Remarks']);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    public function getLoansByBorrower(Request $request)
    {
        $borrowerId = $request->query('borrower_id');

        if (! $borrowerId) {
            return response()->json([], 400);
        }

        $borrower = Borrower::find($borrowerId);
        if (! $borrower) {
            return response()->json([], 404);
        }

        $loans = $borrower->loans()
            ->whereNotIn('status', ['closed', 'cancelled', 'written_off', 'paid'])
            ->orderBy('start_date')
            ->get()
            ->map(function ($loan) {
                return [
                    'id' => $loan->id,
                    'reference_no' => $loan->reference_no,
                    'label' => $loan->reference_no . ' (₱' . number_format($loan->total_payable, 2) . ')',
                ];
            })
            ->values()
            ->all();

        return response()->json($loans);
    }

}

