<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BorrowerPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrower_photo_can_be_uploaded_when_creating_borrower(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'admin']);
        $photo = $this->fakePng('borrower.png');

        $response = $this->actingAs($user)->post(route('borrowers.store'), [
            'first_name' => 'Alex',
            'last_name' => 'Santos',
            'phone' => '555-0100',
            'email' => 'alex@example.com',
            'address' => '123 Market Street',
            'status' => 'active',
            'notes' => 'Prefers SMS reminders.',
            'profile_photo' => $photo,
        ]);

        $borrower = Borrower::firstOrFail();

        $response->assertRedirect(route('borrowers.index'));
        $this->assertNotNull($borrower->profile_photo_path);
        Storage::disk('public')->assertExists($borrower->profile_photo_path);
        $this->assertStringStartsWith('borrower_photos/', $borrower->profile_photo_path);
    }

    public function test_borrower_photo_can_be_replaced_when_updating_borrower(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'admin']);
        $oldPhotoPath = $this->fakePng('old.png')->store('borrower_photos', 'public');
        $borrower = Borrower::create([
            'first_name' => 'Alex',
            'last_name' => 'Santos',
            'phone' => '555-0100',
            'email' => 'alex@example.com',
            'address' => '123 Market Street',
            'status' => 'active',
            'notes' => 'Prefers SMS reminders.',
            'profile_photo_path' => $oldPhotoPath,
        ]);

        $response = $this->actingAs($user)->put(route('borrowers.update', $borrower), [
            'first_name' => 'Alex',
            'last_name' => 'Santos',
            'phone' => '555-0100',
            'email' => 'alex@example.com',
            'address' => '123 Market Street',
            'status' => 'inactive',
            'notes' => 'Updated notes.',
            'profile_photo' => $this->fakePng('new.png'),
        ]);

        $borrower->refresh();

        $response->assertRedirect(route('borrowers.show', $borrower));
        Storage::disk('public')->assertMissing($oldPhotoPath);
        Storage::disk('public')->assertExists($borrower->profile_photo_path);
        $this->assertNotSame($oldPhotoPath, $borrower->profile_photo_path);
    }

    private function fakePng(string $name): UploadedFile
    {
        $onePixelPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        return UploadedFile::fake()->createWithContent($name, $onePixelPng);
    }
}
