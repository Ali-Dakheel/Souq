<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Modules\Cart\Events\CartAbandoned;
use App\Modules\Cart\Models\Cart;
use App\Modules\Cart\Models\CartItem;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\InventoryItem;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CartPruneJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeVariant(): Variant
    {
        $category = Category::create([
            'name' => ['ar' => 'قسم', 'en' => 'Cat'],
            'slug' => 'cat-prune-'.uniqid(),
        ]);

        $product = Product::create([
            'name' => ['ar' => 'منتج', 'en' => 'Prod'],
            'slug' => 'prod-prune-'.uniqid(),
            'category_id' => $category->id,
            'base_price_fils' => 3000,
            'is_available' => true,
        ]);

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-PRUNE-'.uniqid(),
            'is_available' => true,
            'attributes' => [],
        ]);

        InventoryItem::create([
            'variant_id' => $variant->id,
            'quantity_available' => 10,
            'quantity_reserved' => 0,
        ]);

        return $variant;
    }

    public function test_prune_command_deletes_expired_guest_carts_with_items(): void
    {
        Event::fake([CartAbandoned::class]);

        $variant = $this->makeVariant();

        $expiredCart = Cart::create([
            'session_id' => 'expired-session',
            'expires_at' => now()->subDay(),
        ]);

        CartItem::create([
            'cart_id' => $expiredCart->id,
            'variant_id' => $variant->id,
            'quantity' => 2,
            'price_fils_snapshot' => 3000,
        ]);

        $this->artisan('cart:prune-expired')
            ->assertExitCode(0)
            ->expectsOutputToContain('Pruned 1 expired guest cart(s).');

        $this->assertDatabaseMissing('carts', ['id' => $expiredCart->id]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $expiredCart->id]);
        $this->assertDatabaseHas('cart_abandonments', ['session_id' => 'expired-session']);

        Event::assertDispatched(CartAbandoned::class);
    }

    public function test_prune_command_skips_non_expired_carts(): void
    {
        $variant = $this->makeVariant();

        $activeCart = Cart::create([
            'session_id' => 'active-session',
            'expires_at' => now()->addDay(),
        ]);

        CartItem::create([
            'cart_id' => $activeCart->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'price_fils_snapshot' => 3000,
        ]);

        $this->artisan('cart:prune-expired')
            ->assertExitCode(0)
            ->expectsOutputToContain('No expired guest carts found.');

        $this->assertDatabaseHas('carts', ['id' => $activeCart->id]);
    }

    public function test_prune_command_dry_run_does_not_delete(): void
    {
        $variant = $this->makeVariant();

        $expiredCart = Cart::create([
            'session_id' => 'dry-run-session',
            'expires_at' => now()->subHour(),
        ]);

        CartItem::create([
            'cart_id' => $expiredCart->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
            'price_fils_snapshot' => 3000,
        ]);

        $this->artisan('cart:prune-expired', ['--dry-run' => true])
            ->assertExitCode(0);

        // Cart should still exist
        $this->assertDatabaseHas('carts', ['id' => $expiredCart->id]);
        $this->assertDatabaseMissing('cart_abandonments', ['session_id' => 'dry-run-session']);
    }

    public function test_prune_command_skips_empty_expired_carts(): void
    {
        // Cart with no items should not be recorded as abandoned
        Cart::create([
            'session_id' => 'empty-expired-session',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('cart:prune-expired')
            ->assertExitCode(0)
            ->expectsOutputToContain('No expired guest carts found.');
    }
}
