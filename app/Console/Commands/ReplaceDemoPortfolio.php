<?php
namespace App\Console\Commands;
use App\Models\{User, Borrower, Loan, Payment, ActivityLog};
use App\Services\{CalendarLoanService, LoanMetricsService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ReplaceDemoPortfolio extends Command {
    protected $signature = 'lendtrack:replace-demo {--confirmed-test-data}';
    protected $description = 'Back up and replace the authorized test portfolio once; preserve all user accounts.';
    public function handle(): int {
        if (!$this->option('confirmed-test-data')) { $this->error('Explicit test-data confirmation is required.'); return 1; }
        $version = 'calendar-demo-v1';
        if (DB::table('demo_replacements')->where('version', $version)->exists()) { $this->info('Demo replacement already completed; no changes.'); return 0; }
        $admin = User::where('role', 'admin')->firstOrFail();
        DB::transaction(function () use ($version, $admin) {
            // A durable database backup, not an ephemeral Render file.
            $backup = [];
            foreach (['borrowers', 'loans', 'payments', 'activity_logs'] as $table) {
                $backup[$table] = DB::table($table)->lockForUpdate()->get()->toArray();
            }
            DB::table('demo_replacements')->insert(['version' => $version,
                'backup' => json_encode($backup, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('payments')->delete(); DB::table('loans')->delete(); DB::table('borrowers')->delete();
            // Historical audit remains; deleted-record payloads remain available in backup.
            $today = Carbon::today('Asia/Manila');
            $cases = [
                ['Upcoming Weekly', 'weekly', 2, 0, 'active', 'none'],
                ['Due Today', 'monthly', 3, 1, 'active', 'none'],
                ['Overdue Partial', 'monthly', 3, 2, 'overdue', 'partial'],
                ['Fully Paid', 'monthly', 2, 3, 'paid', 'full'],
                ['Pending', 'monthly', 2, 0, 'pending', 'none'],
                ['Cancelled', 'monthly', 2, 0, 'cancelled', 'none'],
                ['Closed', 'monthly', 2, 3, 'closed', 'partial'],
                ['Written Off', 'monthly', 2, 3, 'written_off', 'partial'],
                ['Daily', 'daily', 1, 0, 'active', 'none'],
                ['Every Fifteen Days', 'semi-monthly', 2, 0, 'active', 'none'],
                ['Penalty Outstanding', 'monthly', 2, 3, 'overdue', 'late-full'],
                ['Archived', 'monthly', 2, 0, 'active', 'partial'],
                ['Cash Collection', 'monthly', 2, 2, 'overdue', 'partial'],
                ['GCash Collection', 'monthly', 2, 2, 'overdue', 'partial'],
            ];
            $service = new CalendarLoanService(); $metrics = new LoanMetricsService();
            foreach ($cases as $i => [$label, $frequency, $months, $ago, $status, $mode]) {
                $start = $today->copy()->subMonthsNoOverflow($ago);
                $first = match ($frequency) {
                    'weekly' => $start->copy()->addWeek(), 'daily' => $start->copy()->addDay(),
                    'semi-monthly' => $start->copy()->addDays(15), default => $start->copy()->addMonthNoOverflow(),
                };
                $borrower = Borrower::create(['first_name' => 'DEMO', 'last_name' => $label,
                    'email' => 'demo'.($i+1).'@example.test', 'address' => 'DEMO House, Balingayan, City of Calapan, Oriental Mindoro',
                    'province_code' => '1705200000', 'province_name' => 'Oriental Mindoro',
                    'city_municipality_code' => '1705205000', 'city_municipality_name' => 'City of Calapan',
                    'barangay_code' => '1705205001', 'barangay_name' => 'Balingayan', 'street_house' => 'DEMO House',
                    'status' => $i === 7 ? 'inactive' : 'active', 'notes' => 'SYNTHETIC DEMO RECORD - not a real borrower.']);
                $terms = $service->terms($start->toDateString(), $first->toDateString(), $frequency, $months, 10000, 3);
                $loan = Loan::create(array_merge($terms, ['borrower_id' => $borrower->id, 'user_id' => $admin->id,
                    'reference_no' => 'DEMO-CAL-'.str_pad((string)($i+1), 3, '0', STR_PAD_LEFT),
                    'principal_amount' => 10000, 'interest_rate' => 3, 'frequency' => $frequency,
                    'start_date' => $start, 'first_due_date' => $first, 'penalty_per_missed_due' => 50,
                    'total_paid' => 0, 'status' => $status, 'remarks' => 'SYNTHETIC DEMO RECORD']));
                if ($mode !== 'none') {
                    $date = $mode === 'full' ? $first : $today;
                    $amount = $mode === 'partial' ? 1000 : $loan->total_payable;
                    $penalty = $mode === 'partial' ? $service->quote($loan, $date->toDateString())['penalty_outstanding'] : 0;
                    $p = Payment::create(['loan_id' => $loan->id, 'user_id' => $admin->id, 'amount' => $amount,
                        'penalty_paid' => $penalty, 'paid_at' => $date, 'method' => ['cash','gcash','bank','manual'][$i % 4],
                        'reference_no' => 'DEMO-TXN-'.($i+1), 'remarks' => 'SYNTHETIC DEMO PAYMENT']);
                    $q = $service->quote($loan, $date->toDateString());
                    $p->receipt_snapshot = ['regular_balance' => $q['regular_outstanding'], 'penalty_balance' => $q['penalty_outstanding'],
                        'borrower' => $borrower->full_name, 'received_by' => $admin->name, 'loan_reference' => $loan->reference_no];
                    $p->save();
                }
                $metrics->syncLoanTotals($loan, $today);
                if (in_array($status, ['closed', 'written_off'])) {
                    $loan->update(['closed_at' => now(), 'closed_by' => $admin->id, 'close_reason' => $status === 'closed' ? 'settled_outside' : 'written_off', 'close_remarks' => 'DEMO closure']);
                }
                ActivityLog::create(['user_id' => $admin->id, 'subject_type' => Loan::class, 'subject_id' => $loan->id,
                    'action' => 'created', 'description' => 'Created synthetic demo loan: '.$label, 'changes' => ['demo_version' => $version]]);
                if ($label === 'Archived') {
                    $loan->payments()->delete(); $loan->unsetRelation('payments'); $metrics->syncLoanTotals($loan, $today);
                    $loan->delete(); $borrower->delete();
                }
            }
        });
        $this->info('Demo portfolio replaced; backup stored in demo_replacements. User accounts preserved.');
        return 0;
    }
}
