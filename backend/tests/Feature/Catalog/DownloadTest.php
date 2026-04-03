<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\DownloadableLinkPurchase;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\DownloadService;
use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createDownloadableProduct(): Product
    {
        $category = Category::create([
            'name' => ['ar' => 'قسم', 'en' => 'Category'],
            'slug' => 'cat-'.uniqid(),
        ]);

        $product = Product::create([
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $category->id,
            'base_price_fils' => 10000,
            'is_available' => true,
            'product_type' => 'downloadable',
        ]);

        $product->downloadableLinks()->create([
            'name_en' => 'Ebook PDF',
            'name_ar' => 'كتاب إلكتروني',
            'file_path' => 'downloads/ebook.pdf',
            'downloads_allowed' => 3,
            'sort_order' => 0,
        ]);

        return $product;
    }

    private function createDownloadablePurchase(User $user): DownloadableLinkPurchase
    {
        $product = $this->createDownloadableProduct();
        $link = $product->downloadableLinks()->first();

        // Create a fake order for the user
        $order = Order::create([
            'order_number' => 'ORD-'.uniqid(),
            'user_id' => $user->id,
            'order_status' => 'paid',
            'subtotal_fils' => 10000,
            'coupon_discount_fils' => 0,
            'vat_fils' => 1000,
            'delivery_fee_fils' => 0,
            'total_fils' => 11000,
            'payment_method' => 'card',
        ]);

        $orderItem = $order->items()->create([
            'product_id' => $product->id,
            'variant_id' => null,
            'sku' => 'SKU-'.uniqid(),
            'product_name' => ['ar' => 'منتج', 'en' => 'Product'],
            'variant_attributes' => null,
            'quantity' => 1,
            'price_fils_per_unit' => 10000,
            'total_fils' => 10000,
        ]);

        return DownloadableLinkPurchase::create([
            'downloadable_link_id' => $link->id,
            'order_item_id' => $orderItem->id,
            'order_id' => $order->id,
            'download_count' => 0,
            'expires_at' => now()->addDays(30),
        ]);
    }

    // -----------------------------------------------------------------------
    // Token generation
    // -----------------------------------------------------------------------

    public function test_generate_token_creates_valid_signed_token(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token);
        [$payload, $sig] = explode('.', $token);
        $this->assertNotEmpty($payload);
        $this->assertNotEmpty($sig);
    }

    public function test_token_payload_contains_required_fields(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        [$payload] = explode('.', $token);
        $padding = str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $json = base64_decode(strtr($payload.$padding, '-_', '+/'), strict: true);
        $data = json_decode($json, associative: true);

        $this->assertArrayHasKey('link_id', $data);
        $this->assertArrayHasKey('purchase_id', $data);
        $this->assertArrayHasKey('user_id', $data);
        $this->assertArrayHasKey('expires_at', $data);
        $this->assertEquals($purchase->downloadable_link_id, $data['link_id']);
        $this->assertEquals($purchase->id, $data['purchase_id']);
        $this->assertEquals($user->id, $data['user_id']);
    }

    // -----------------------------------------------------------------------
    // Token validation
    // -----------------------------------------------------------------------

    public function test_validate_token_accepts_valid_token(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        $validated = $service->validateAndDecodeToken($token, $user->id);

        $this->assertEquals($purchase->id, $validated->id);
    }

    public function test_validate_token_rejects_invalid_format(): void
    {
        $user = User::factory()->create();

        $service = app(DownloadService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->validateAndDecodeToken('invalid-token', $user->id);
    }

    public function test_validate_token_rejects_tampered_signature(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        [$payload, $sig] = explode('.', $token);
        $fakeSig = str_repeat('a', strlen($sig));
        $tamperedToken = $payload.'.'.$fakeSig;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token signature');
        $service->validateAndDecodeToken($tamperedToken, $user->id);
    }

    public function test_validate_token_rejects_expired_token(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        $service = app(DownloadService::class);

        // Create token at an old time
        $oldTime = now()->subHours(25);
        Date::setTestNow($oldTime);
        $token = $service->generateToken($purchase);

        // Now advance past the 24-hour expiry window
        Date::setTestNow($oldTime->addHours(25));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Token has expired');
        $service->validateAndDecodeToken($token, $user->id);
    }

    public function test_validate_token_rejects_wrong_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user1);

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Token user mismatch');
        $service->validateAndDecodeToken($token, $user2->id);
    }

    public function test_validate_token_rejects_exhausted_downloads(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        // Update link to allow only 1 download
        $purchase->downloadableLink->update(['downloads_allowed' => 1]);

        // Simulate 1 download already used
        $purchase->update(['download_count' => 1]);

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Download limit reached');
        $service->validateAndDecodeToken($token, $user->id);
    }

    public function test_validate_token_allows_unlimited_downloads(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        // Update link to allow unlimited downloads
        $purchase->downloadableLink->update(['downloads_allowed' => 0]);

        // Simulate many downloads
        $purchase->update(['download_count' => 1000]);

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        // Should not throw
        $validated = $service->validateAndDecodeToken($token, $user->id);
        $this->assertEquals($purchase->id, $validated->id);
    }

    // -----------------------------------------------------------------------
    // Download recording
    // -----------------------------------------------------------------------

    public function test_record_download_increments_counter(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        $initialCount = $purchase->download_count;

        $service = app(DownloadService::class);
        $service->recordDownload($purchase);

        $purchase->refresh();
        $this->assertEquals($initialCount + 1, $purchase->download_count);
    }

    public function test_record_download_updates_timestamp(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        $service = app(DownloadService::class);
        $service->recordDownload($purchase);

        $purchase->refresh();
        $this->assertNotNull($purchase->last_downloaded_at);
    }

    // -----------------------------------------------------------------------
    // HTTP endpoint
    // -----------------------------------------------------------------------

    public function test_download_endpoint_requires_auth(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        $response = $this->getJson("/api/v1/downloads/{$token}");

        $response->assertUnauthorized();
    }

    public function test_download_endpoint_returns_file(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        // Create a fake file
        Storage::disk('local')->put('downloads/ebook.pdf', 'test file content');

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/downloads/{$token}");

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_download_endpoint_increments_counter(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        Storage::disk('local')->put('downloads/ebook.pdf', 'test file content');

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/downloads/{$token}");

        $purchase->refresh();
        $this->assertEquals(1, $purchase->download_count);
    }

    public function test_download_endpoint_returns_404_for_missing_file(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        // Explicitly delete any existing file
        Storage::disk('local')->delete('downloads/ebook.pdf');

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/downloads/{$token}");

        $response->assertNotFound();
    }

    public function test_download_endpoint_rejects_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/downloads/invalid-token');

        $response->assertForbidden();
        $response->assertJsonStructure(['message']);
    }

    public function test_download_endpoint_rejects_expired_token(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        $oldTime = now()->subHours(25);
        Date::setTestNow($oldTime);
        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);
        Date::setTestNow($oldTime->addHours(25));

        Storage::disk('local')->put('downloads/ebook.pdf', 'test file content');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/downloads/{$token}");

        $response->assertForbidden();
        $response->assertJsonFragment(['message' => 'Token has expired.']);
    }

    public function test_download_endpoint_rejects_exhausted_downloads(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user);

        $purchase->downloadableLink->update(['downloads_allowed' => 1]);
        $purchase->update(['download_count' => 1]);

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        Storage::disk('local')->put('downloads/ebook.pdf', 'test file content');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/downloads/{$token}");

        $response->assertForbidden();
        $response->assertJsonFragment(['message' => 'Download limit reached.']);
    }

    public function test_download_endpoint_prevents_cross_user_access(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $purchase = $this->createDownloadablePurchase($user1);

        Storage::disk('local')->put('downloads/ebook.pdf', 'test file content');

        $service = app(DownloadService::class);
        $token = $service->generateToken($purchase);

        $response = $this->actingAs($user2, 'sanctum')
            ->getJson("/api/v1/downloads/{$token}");

        $response->assertForbidden();
        $response->assertJsonFragment(['message' => 'Token user mismatch.']);
    }
}
