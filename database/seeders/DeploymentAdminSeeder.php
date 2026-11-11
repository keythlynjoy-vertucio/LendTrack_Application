<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DeploymentAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = getenv('DEPLOY_ADMIN_EMAIL') ?: '';
        $password = getenv('DEPLOY_ADMIN_PASSWORD') ?: '';
        if ($email === '' && $password === '') {
            return;
        }
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            throw new \RuntimeException('Set a valid deployment admin email and password of at least 12 characters.');
        }
        // Never reset or promote an existing account on a redeploy.
        if (User::where('email', $email)->exists()) {
            return;
        }
        User::create([
            'name' => 'LendTrack Admin',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'admin',
        ]);
    }
}
