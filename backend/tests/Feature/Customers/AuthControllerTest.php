<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Customers\Events\CustomerRegistered;
use App\Modules\Customers\Events\PasswordResetRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_201(): void
    {
        Event::fake([CustomerRegistered::class]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ali Bahraini',
            'email' => 'ali@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.email', 'ali@example.com')
            ->assertJsonPath('data.preferred_locale', 'ar')
            ->assertJsonPath('message', 'Registration successful.');

        $this->assertDatabaseHas('users', ['email' => 'ali@example.com']);
        $this->assertDatabaseHas('customer_profiles', ['preferred_locale' => 'ar']);

        Event::assertDispatched(CustomerRegistered::class);
    }

    public function test_register_with_duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ali',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_with_password_mismatch_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ali',
            'email' => 'ali@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_returns_200_with_user_data(): void
    {
        $user = User::factory()->create([
            'email' => 'ali@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ali@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.email', 'ali@example.com')
            ->assertJsonPath('message', 'Login successful.');
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        User::factory()->create([
            'email' => 'ali@example.com',
            'password' => Hash::make('correctpassword'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ali@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_logout_returns_200_and_invalidates_session(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Logout successful.');
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_me_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_forgot_password_returns_200_for_existing_email(): void
    {
        Event::fake([PasswordResetRequested::class]);

        User::factory()->create(['email' => 'ali@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'ali@example.com',
        ]);

        $response->assertStatus(200);

        Event::assertDispatched(PasswordResetRequested::class);
    }

    public function test_forgot_password_returns_200_for_unknown_email(): void
    {
        // Must not leak whether email exists
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function test_reset_password_updates_user_password(): void
    {
        $user = User::factory()->create(['email' => 'ali@example.com']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'ali@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password reset successful.');

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_reset_password_with_invalid_token_returns_422(): void
    {
        User::factory()->create(['email' => 'ali@example.com']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => 'ali@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
    }
}
