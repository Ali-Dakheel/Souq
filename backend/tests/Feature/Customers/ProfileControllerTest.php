<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Customers\Models\CustomerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_profile_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        CustomerProfile::create([
            'user_id' => $user->id,
            'preferred_locale' => 'ar',
            'marketing_consent' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/customers/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.preferred_locale', 'ar');
    }

    public function test_show_creates_profile_if_missing(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/customers/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('customer_profiles', ['user_id' => $user->id]);
    }

    public function test_show_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/customers/profile');

        $response->assertStatus(401);
    }

    public function test_update_changes_locale(): void
    {
        $user = User::factory()->create();
        CustomerProfile::create(['user_id' => $user->id, 'preferred_locale' => 'ar', 'marketing_consent' => false]);

        $response = $this->actingAs($user)->putJson('/api/v1/customers/profile', [
            'preferred_locale' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.preferred_locale', 'en');

        $this->assertDatabaseHas('customer_profiles', ['user_id' => $user->id, 'preferred_locale' => 'en']);
    }

    public function test_update_with_invalid_locale_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/customers/profile', [
            'preferred_locale' => 'fr',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['preferred_locale']);
    }

    public function test_change_password_returns_200(): void
    {
        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);

        $response = $this->actingAs($user)->postJson('/api/v1/customers/change-password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_change_password_with_wrong_current_returns_422(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correctpassword')]);

        $response = $this->actingAs($user)->postJson('/api/v1/customers/change-password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_profile_endpoints_require_authentication(): void
    {
        $this->putJson('/api/v1/customers/profile', [])->assertStatus(401);
        $this->postJson('/api/v1/customers/change-password', [])->assertStatus(401);
    }
}
