<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TapTransactionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tap_transaction_list_renders(): void
    {
        $admin = $this->createAdmin();
        $order = Order::factory()->create();
        TapTransaction::factory()
            ->for($order)
            ->create([
                'status' => 'captured',
                'amount_fils' => 15000,
            ]);

        $this->actingAs($admin)
            ->get('/admin/tap-transactions')
            ->assertSuccessful()
            ->assertSeeText('Transactions');
    }

    public function test_tap_transaction_view_renders(): void
    {
        $admin = $this->createAdmin();
        $order = Order::factory()->create();
        $transaction = TapTransaction::factory()
            ->for($order)
            ->create([
                'status' => 'captured',
                'amount_fils' => 15000,
            ]);

        $this->actingAs($admin)
            ->get("/admin/tap-transactions/{$transaction->id}")
            ->assertSuccessful();
    }

    private function createAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
