<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, Borrower, Loan, Payment, User};
use App\Services\LoanMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Notification};
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

class MobileCompletionTest extends \Illuminate\Foundation\Testing\TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Mobile tests require an isolated in-memory database.');
        }
        return $app;
    }

    private function signIn(string $role = 'admin'): User
    {
        $user = User::create(['name' => 'Test user', 'email' => Str::uuid().'@example.test',
            'password' => 'test-password', 'role' => $role]);
        Sanctum::actingAs($user);
        return $user;
    }

    private function loan(User $user, string $status = 'active'): Loan
    {
        $borrower = Borrower::create(['first_name' => 'Test', 'last_name' => 'Borrower']);
        return Loan::create(['borrower_id' => $borrower->id, 'user_id' => $user->id,
            'reference_no' => 'TEST-'.Str::uuid(), 'principal_amount' => 1000,
            'interest_rate' => 3, 'term_months' => 4, 'frequency' => 'weekly',
            'start_date' => today()->toDateString(), 'first_due_date' => today()->addWeek()->toDateString(),
            'due_date' => today()->addWeeks(4)->toDateString(), 'total_payable' => 1030,
            'total_paid' => 0, 'status' => $status]);
    }

    public function test_admin_routes_require_authentication_and_role(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->signIn('staff');
        foreach (['activity-logs', 'discrepancies', 'records/loans', 'reports/collections'] as $route) {
            $this->getJson('/api/'.$route)->assertForbidden();
        }
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('borrowers', 0);
        $this->postJson('/api/records/loans/1', ['action' => 'delete', 'reason' => 'test'])->assertForbidden();
    }

    public function test_case_insensitive_search_and_filter(): void
    {
        $user = $this->signIn();
        $loan = $this->loan($user, 'Closed');
        $this->getJson('/api/loans?status=closed&search='.$loan->reference_no)
            ->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/loans?status=paid')->assertJsonPath('total', 0);
        $this->getJson('/api/borrowers?status=active&search=Borrower')->assertJsonPath('total', 1);
    }

    public function test_terminal_status_is_preserved_when_totals_are_synced(): void
    {
        $user = $this->signIn();
        $loan = $this->loan($user, 'Closed');
        (new LoanMetricsService())->syncLoanTotals($loan);
        $this->assertSame('closed', $loan->fresh()->status);
    }

    public function test_payment_retry_penalties_overpayment_receipt_and_report(): void
    {
        Notification::fake();
        $user = $this->signIn('staff');
        $loan = $this->loan($user);
        $body = ['client_request_id' => (string) Str::uuid(), 'amount' => '250.00',
            'penalty_paid' => '50.00', 'paid_at' => today()->toDateString(), 'method' => 'cash'];
        $path = '/api/loans/'.$loan->id.'/payments';
        $first = $this->postJson($path, $body)->assertCreated()->assertJsonPath('replayed', false);
        $id = $first->json('data.id');
        $this->postJson($path, $body)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('replayed', true);
        $this->assertSame(1, Payment::count());
        $this->assertEquals(250, $loan->fresh()->total_paid);
        $this->postJson($path, array_merge($body, ['amount' => '251.00']))->assertConflict();
        $this->postJson($path, array_merge($body, ['client_request_id' => (string) Str::uuid(), 'amount' => '781.00']))->assertUnprocessable();
        $this->getJson('/api/payments/'.$id.'/receipt')->assertOk()->assertJsonPath('total_received', '300.00');
        $this->signIn('admin');
        $report = $this->getJson('/api/reports/collections')->assertOk();
        $this->assertEquals(300, $report->json('total_collected'));
        $this->assertEquals(50, $report->json('penalty_collected'));
    }

    public function test_archive_dependencies_restore_and_audit(): void
    {
        $user = $this->signIn();
        $loan = $this->loan($user);
        $payment = Payment::create(['loan_id' => $loan->id, 'user_id' => $user->id,
            'amount' => 100, 'paid_at' => today(), 'method' => 'cash']);
        $delete = ['action' => 'delete', 'reason' => 'Correcting test record'];
        $restore = ['action' => 'restore', 'reason' => 'Restoring test record'];
        $this->postJson('/api/records/borrowers/'.$loan->borrower_id, $delete)->assertUnprocessable();
        $this->postJson('/api/records/loans/'.$loan->id, $delete)->assertUnprocessable();
        $this->postJson('/api/records/payments/'.$payment->id, $delete)->assertOk();
        $this->assertEquals(0, $loan->fresh()->total_paid);
        $this->postJson('/api/records/loans/'.$loan->id, $delete)->assertOk();
        $this->postJson('/api/records/payments/'.$payment->id, $restore)->assertUnprocessable();
        $this->postJson('/api/records/loans/'.$loan->id, $restore)->assertOk();
        $this->postJson('/api/records/payments/'.$payment->id, $restore)->assertOk();
        $this->assertEquals(100, $loan->fresh()->total_paid);
        $this->assertSame(4, ActivityLog::count());
    }

    public function test_ncr_address_is_stored_as_region_and_invalid_hierarchy_is_rejected(): void
    {
        $this->signIn();
        Http::preventStrayRequests();
        Http::fake([
            'psgc.cloud/api/regions/1300000000/cities-municipalities' => Http::response([
                ['code' => '1381300000', 'name' => 'Quezon City']]),
            'psgc.cloud/api/cities-municipalities/1381300000/barangays' => Http::response([
                ['code' => '1381300001', 'name' => 'Fixture barangay']]),
        ]);
        $body = ['first_name' => 'NCR', 'last_name' => 'Test', 'region_code' => '1300000000',
            'province_code' => null, 'city_municipality_code' => '1381300000',
            'barangay_code' => '1381300001', 'street_house' => 'House 1'];
        $result = $this->postJson('/api/borrowers', $body)->assertCreated();
        $result->assertJsonPath('data.province_code', null)->assertJsonPath('data.region_code', '1300000000');
        $this->postJson('/api/borrowers', array_merge($body, ['barangay_code' => '0000000000']))
            ->assertUnprocessable()->assertJsonValidationErrors('barangay_code');
        $this->assertSame(1, Borrower::count());
        $this->patchJson('/api/borrowers/'.$result->json('data.id'), ['first_name' => 'Edited', 'last_name' => 'Test'])
            ->assertOk()->assertJsonPath('data.region_code', '1300000000');
    }

    public function test_legacy_schedule_is_flagged_without_rewriting_it(): void
    {
        $user = $this->signIn();
        $loan = $this->loan($user);
        $loan->update(['first_due_date' => null, 'due_date' => today()->addYear()]);
        $this->getJson('/api/discrepancies')->assertOk()->assertJsonCount(2, 'data.0.warnings');
        $this->assertNull($loan->fresh()->first_due_date);
    }
    public function test_small_schedule_never_has_negative_installments(): void
    {
        $loan = $this->loan($this->signIn());
        $loan->update(['total_payable' => '0.04', 'term_months' => 6]);
        $rows = (new LoanMetricsService())->collectionSchedule($loan);
        $this->assertEquals(0.04, round(array_sum(array_column($rows, 'expected')), 2));
        foreach ($rows as $row) $this->assertGreaterThanOrEqual(0, $row['expected']);
    }

    public function test_restoring_payment_cannot_overpay_loan(): void
    {
        $user = $this->signIn();
        $loan = $this->loan($user);
        $attributes = ['loan_id' => $loan->id, 'user_id' => $user->id,
            'amount' => 1030, 'paid_at' => today(), 'method' => 'cash'];
        $old = Payment::create($attributes);
        $old->delete();
        Payment::create($attributes);
        $this->postJson('/api/records/payments/'.$old->id, ['action' => 'restore', 'reason' => 'Test'])
            ->assertUnprocessable();
        $this->assertTrue($old->fresh()->trashed());
    }


    public function test_admin_can_create_only_staff_and_password_is_not_exposed(): void
    {
        $this->signIn('admin');
        $payload = ['name' => 'New Staff', 'email' => 'NEWSTAFF@example.test',
            'password' => 'Unique-test-password', 'password_confirmation' => 'Unique-test-password', 'role' => 'admin'];
        $response = $this->postJson('/api/staff', $payload)->assertCreated()
            ->assertJsonPath('data.role', 'staff')->assertJsonMissingPath('data.password');
        $staff = User::findOrFail($response->json('data.id'));
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($payload['password'], $staff->password));
        $this->assertStringNotContainsString($payload['password'], ActivityLog::latest('id')->first()->toJson());
        $this->postJson('/api/staff', $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_staff_cannot_create_accounts(): void
    {
        $this->signIn('staff');
        $this->postJson('/api/staff', [])->assertForbidden();
    }

    public function test_staff_creation_requires_confirmation_and_strong_length(): void
    {
        $this->signIn('admin');
        $this->postJson('/api/staff', ['name' => 'Staff', 'email' => 'staff@example.test',
            'password' => 'short', 'password_confirmation' => 'other'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
