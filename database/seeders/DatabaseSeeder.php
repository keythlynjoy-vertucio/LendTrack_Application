<?php

namespace Database\Seeders;

use App\Models\Borrower;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()->firstOrNew(['email' => 'lendtrack.admin@gmail.com']);
        $admin->name = 'LendTrack Admin';
        $admin->role = 'admin';
        if (! $admin->exists) {
            $admin->password = Hash::make('password');
        }
        $admin->save();

        $staff = User::query()->firstOrNew(['email' => 'lendtrack.staff@gmail.com']);
        $staff->name = 'LendTrack Staff';
        $staff->role = 'staff';
        if (! $staff->exists) {
            $staff->password = Hash::make('password');
        }
        $staff->save();

        if (Borrower::count() > 0) {
            return;
        }

        $borrowers = collect(range(1, 12))->map(function () {
            return Borrower::create([
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'phone' => fake()->optional()->phoneNumber(),
                'email' => fake()->optional()->safeEmail(),
                'address' => fake()->optional()->address(),
                'status' => fake()->randomElement(['active', 'active', 'active', 'inactive']),
                'notes' => fake()->optional()->sentence(),
            ]);
        });

        $users = collect([$admin, $staff]);

        $borrowers->each(function (Borrower $borrower) use ($users) {
            $loanCount = fake()->numberBetween(1, 2);
            for ($i = 0; $i < $loanCount; $i++) {
                $principal = fake()->randomFloat(2, 2000, 50000);
                $interest = fake()->randomFloat(2, 1, 15);
                $totalPayable = round($principal + ($principal * ($interest / 100)), 2);

                $start = fake()->dateTimeBetween('-6 months', 'now');
                $due = (clone $start)->modify('+'.fake()->numberBetween(1, 12).' months');

                $loan = Loan::create([
                    'borrower_id' => $borrower->id,
                    'user_id' => $users->random()->id,
                    'reference_no' => 'LN-SEED-'.Str::upper(Str::random(8)),
                    'principal_amount' => $principal,
                    'interest_rate' => $interest,
                    'term_months' => fake()->numberBetween(1, 12),
                    'start_date' => $start->format('Y-m-d'),
                    'due_date' => $due->format('Y-m-d'),
                    'total_payable' => $totalPayable,
                    'total_paid' => 0,
                    'status' => 'active',
                    'remarks' => fake()->optional()->sentence(),
                ]);

                $paymentTotal = 0.0;
                $paymentsToCreate = fake()->numberBetween(0, 4);
                for ($p = 0; $p < $paymentsToCreate; $p++) {
                    $amount = fake()->randomFloat(2, 200, min(3000, max(200, $totalPayable - $paymentTotal)));
                    $paymentTotal += $amount;

                    Payment::create([
                        'loan_id' => $loan->id,
                        'user_id' => $users->random()->id,
                        'amount' => $amount,
                        'paid_at' => fake()->dateTimeBetween($start, 'now')->format('Y-m-d'),
                        'method' => fake()->randomElement(['cash', 'gcash', 'bank', 'manual']),
                        'reference_no' => fake()->optional()->bothify('REF-####??'),
                        'remarks' => fake()->optional()->sentence(),
                    ]);
                }

                $loan->total_paid = round((float) $loan->payments()->sum('amount'), 2);
                if ($loan->total_paid >= (float) $loan->total_payable) {
                    $loan->status = 'paid';
                } elseif ($loan->due_date->isPast()) {
                    $loan->status = 'overdue';
                }
                $loan->save();
            }
        });
    }
}
