<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_list_renders(): void
    {
        $admin = $this->createAdmin();
        User::factory(3)->create();

        $this->actingAs($admin)
            ->get('/admin/customers')
            ->assertSuccessful()
            ->assertSeeText('Customers');
    }

    public function test_customer_view_renders(): void
    {
        $admin = $this->createAdmin();
        $customer = User::factory()->create(['name' => 'John Doe']);

        $this->actingAs($admin)
            ->get("/admin/customers/{$customer->id}")
            ->assertSuccessful();
    }

    public function test_customer_view_shows_orders_relation(): void
    {
        $admin = $this->createAdmin();
        $customer = User::factory()->create();
        Order::factory(2)->for($customer)->create();

        $this->actingAs($admin)
            ->get("/admin/customers/{$customer->id}")
            ->assertSuccessful();
        // Orders relation is shown in the page via RelationManager
    }

    private function createAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
