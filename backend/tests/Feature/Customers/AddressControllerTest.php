<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Models\User;
use App\Modules\Customers\Events\AddressAdded;
use App\Modules\Customers\Events\AddressDeleted;
use App\Modules\Customers\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AddressControllerTest extends TestCase
{
    use RefreshDatabase;

    private function shippingPayload(array $overrides = []): array
    {
        return array_merge([
            'address_type' => 'shipping',
            'recipient_name' => 'Ali Bahraini',
            'phone' => '+97312345678',
            'governorate' => 'Manama',
            'street_address' => 'Road 123, Block 456',
        ], $overrides);
    }

    public function test_index_returns_addresses_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        CustomerAddress::create(array_merge($this->shippingPayload(), ['user_id' => $user->id]));
        CustomerAddress::create(array_merge($this->shippingPayload(), ['user_id' => $other->id]));

        $response = $this->actingAs($user)->getJson('/api/v1/customers/addresses');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_type(): void
    {
        $user = User::factory()->create();
        CustomerAddress::create(array_merge($this->shippingPayload(), ['user_id' => $user->id]));
        CustomerAddress::create(array_merge($this->shippingPayload(['address_type' => 'billing']), ['user_id' => $user->id]));

        $response = $this->actingAs($user)->getJson('/api/v1/customers/addresses?type=shipping');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.address_type', 'shipping');
    }

    public function test_store_creates_address_and_fires_event(): void
    {
        Event::fake([AddressAdded::class]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/customers/addresses', $this->shippingPayload());

        $response->assertStatus(201)
            ->assertJsonPath('data.governorate', 'Manama')
            ->assertJsonPath('data.address_type', 'shipping');

        $this->assertDatabaseHas('customer_addresses', ['user_id' => $user->id, 'governorate' => 'Manama']);

        Event::assertDispatched(AddressAdded::class);
    }

    public function test_store_sets_default_and_unsets_previous(): void
    {
        $user = User::factory()->create();
        $existing = CustomerAddress::create(array_merge($this->shippingPayload(['is_default' => true]), ['user_id' => $user->id]));

        $this->actingAs($user)->postJson('/api/v1/customers/addresses', $this->shippingPayload(['is_default' => true]));

        $this->assertDatabaseHas('customer_addresses', ['id' => $existing->id, 'is_default' => false]);
    }

    public function test_update_changes_phone(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::create(array_merge($this->shippingPayload(), ['user_id' => $user->id]));

        $response = $this->actingAs($user)->putJson("/api/v1/customers/addresses/{$address->id}", [
            'phone' => '+97398765432',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.phone', '+97398765432');
    }

    public function test_user_cannot_update_another_users_address(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $address = CustomerAddress::create(array_merge($this->shippingPayload(), ['user_id' => $other->id]));

        $response = $this->actingAs($user)->putJson("/api/v1/customers/addresses/{$address->id}", [
            'phone' => '+97312345678',
        ]);

        $response->assertStatus(404);
    }

    public function test_destroy_deletes_address_and_fires_event(): void
    {
        Event::fake([AddressDeleted::class]);

        $user = User::factory()->create();
        // Create two shipping addresses so deletion is allowed
        CustomerAddress::create(array_merge($this->shippingPayload(), ['user_id' => $user->id]));
        $address = CustomerAddress::create(array_merge($this->shippingPayload(), ['user_id' => $user->id]));

        $response = $this->actingAs($user)->deleteJson("/api/v1/customers/addresses/{$address->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id]);

        Event::assertDispatched(AddressDeleted::class);
    }

    public function test_cannot_delete_only_shipping_address(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::create(array_merge($this->shippingPayload(), ['user_id' => $user->id]));

        $response = $this->actingAs($user)->deleteJson("/api/v1/customers/addresses/{$address->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['address']);
    }

    public function test_set_default_marks_address_as_default(): void
    {
        $user = User::factory()->create();
        $address = CustomerAddress::create(array_merge($this->shippingPayload(['is_default' => false]), ['user_id' => $user->id]));

        $response = $this->actingAs($user)->putJson("/api/v1/customers/addresses/{$address->id}/set-default");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_default', true);
    }

    public function test_addresses_require_authentication(): void
    {
        $this->getJson('/api/v1/customers/addresses')->assertStatus(401);
        $this->postJson('/api/v1/customers/addresses', [])->assertStatus(401);
    }
}
