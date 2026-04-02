<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Refund;
use App\Modules\Payments\Models\TapTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RefundResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_list_renders(): void
    {
        $admin = $this->createAdmin();
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $transaction = TapTransaction::factory()
            ->for($order)
            ->create(['status' => 'captured']);
        Refund::factory()
            ->for($order)
            ->for($transaction, 'tapTransaction')
            ->for($customer, 'requestedBy')
            ->create(['status' => 'pending']);

        $this->actingAs($admin)
            ->get('/admin/refunds')
            ->assertSuccessful()
            ->assertSeeText('Refunds');
    }

    public function test_refund_pending_status_shows_approve_reject_actions(): void
    {
        $admin = $this->createAdmin();
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();
        $transaction = TapTransaction::factory()
            ->for($order)
            ->create(['status' => 'captured']);
        $refund = Refund::factory()
            ->for($order)
            ->for($transaction, 'tapTransaction')
            ->for($customer, 'requestedBy')
            ->create(['status' => 'pending']);

        $this->actingAs($admin)
            ->get('/admin/refunds')
            ->assertSuccessful()
            ->assertSeeText('Approve')
            ->assertSeeText('Reject');
    }

    private function createAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
