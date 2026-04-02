<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Cart\Models\Coupon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CouponResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_coupon_list_renders(): void
    {
        $admin = $this->createAdmin();
        Coupon::factory()->create([
            'code' => 'TEST10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ]);

        $this->actingAs($admin)
            ->get('/admin/coupons')
            ->assertSuccessful()
            ->assertSeeText('Coupons');
    }

    public function test_coupon_create_form_renders(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->get('/admin/coupons/create')
            ->assertSuccessful()
            ->assertSeeText('Code');
    }

    public function test_coupon_edit_form_renders(): void
    {
        $admin = $this->createAdmin();
        $coupon = Coupon::factory()->create(['code' => 'TEST10']);

        $this->actingAs($admin)
            ->get("/admin/coupons/{$coupon->id}/edit")
            ->assertSuccessful();
    }

    public function test_coupon_list_shows_data(): void
    {
        $admin = $this->createAdmin();
        Coupon::factory()->create([
            'code' => 'SUMMER20',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/coupons')
            ->assertSuccessful()
            ->assertSeeText('SUMMER20')
            ->assertSeeText('percentage');
    }

    private function createAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
