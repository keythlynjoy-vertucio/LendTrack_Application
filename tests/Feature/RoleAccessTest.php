<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_open_admin_only_pages(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/reports')->assertForbidden();
        $this->actingAs($staff)->get('/activity-logs')->assertForbidden();
        $this->actingAs($staff)->get('/borrowers/trashed')->assertForbidden();
        $this->actingAs($staff)->get('/loans/trashed')->assertForbidden();
    }

    public function test_staff_navigation_hides_admin_only_links(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('View reports')
            ->assertDontSee('Reports')
            ->assertDontSee('Activity logs');
    }

    public function test_admin_can_open_admin_only_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/reports')->assertOk();
        $this->actingAs($admin)->get('/activity-logs')->assertOk();
        $this->actingAs($admin)->get('/borrowers/trashed')->assertOk();
        $this->actingAs($admin)->get('/loans/trashed')->assertOk();
    }
}
