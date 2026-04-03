<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTypeTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createCategory(): Category
    {
        return Category::create([
            'name' => ['ar' => 'قسم', 'en' => 'Category'],
            'slug' => 'cat-'.uniqid(),
        ]);
    }

    private function createProduct(string $productType = 'simple'): Product
    {
        return Product::create([
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $this->createCategory()->id,
            'base_price_fils' => 10000,
            'is_available' => true,
            'product_type' => $productType,
        ]);
    }

    // -----------------------------------------------------------------------
    // 1. product_type field validation
    // -----------------------------------------------------------------------

    public function test_store_product_with_type_simple(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $this->createCategory()->id,
            'base_price_fils' => 10000,
            'is_available' => true,
            'product_type' => 'simple',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.product_type', 'simple');
    }

    public function test_store_product_with_type_configurable(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $this->createCategory()->id,
            'base_price_fils' => 10000,
            'is_available' => true,
            'product_type' => 'configurable',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.product_type', 'configurable');
    }

    public function test_store_product_with_type_bundle(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $this->createCategory()->id,
            'base_price_fils' => 10000,
            'is_available' => true,
            'product_type' => 'bundle',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.product_type', 'bundle');
    }

    public function test_store_product_with_type_downloadable(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $this->createCategory()->id,
            'base_price_fils' => 10000,
            'is_available' => true,
            'product_type' => 'downloadable',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.product_type', 'downloadable');
    }

    public function test_store_product_with_type_virtual(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $this->createCategory()->id,
            'base_price_fils' => 10000,
            'is_available' => true,
            'product_type' => 'virtual',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.product_type', 'virtual');
    }

    public function test_store_product_with_invalid_type(): void
    {
        $response = $this->postJson('/api/v1/products', [
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $this->createCategory()->id,
            'base_price_fils' => 10000,
            'is_available' => true,
            'product_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('product_type', $response->json('errors'));
    }

    public function test_update_product_type(): void
    {
        $product = $this->createProduct('simple');

        $response = $this->putJson("/api/v1/products/{$product->id}", [
            'product_type' => 'bundle',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.product_type', 'bundle');
        $this->assertEquals('bundle', $product->fresh()->product_type);
    }

    // -----------------------------------------------------------------------
    // 2. Bundle option CRUD
    // -----------------------------------------------------------------------

    public function test_create_bundle_option_on_bundle_product(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('bundle');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bundle-options", [
                'name_en' => 'Choose Size',
                'name_ar' => 'اختر الحجم',
                'required' => true,
                'sort_order' => 1,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name_en', 'Choose Size');
        $response->assertJsonPath('data.name_ar', 'اختر الحجم');
        $response->assertJsonPath('data.required', true);
        $response->assertJsonPath('data.sort_order', 1);
    }

    public function test_create_bundle_option_on_non_bundle_product(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('simple');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bundle-options", [
                'name_en' => 'Choose Size',
                'name_ar' => 'اختر الحجم',
                'required' => true,
                'sort_order' => 1,
            ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('product_type', $response->json('errors'));
    }

    public function test_create_bundle_option_requires_name_en(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('bundle');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bundle-options", [
                'name_ar' => 'اختر الحجم',
                'required' => true,
                'sort_order' => 1,
            ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('name_en', $response->json('errors'));
    }

    public function test_create_bundle_option_requires_name_ar(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('bundle');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bundle-options", [
                'name_en' => 'Choose Size',
                'required' => true,
                'sort_order' => 1,
            ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('name_ar', $response->json('errors'));
    }

    public function test_add_product_to_bundle_option(): void
    {
        $user = User::factory()->create();
        $bundleProduct = $this->createProduct('bundle');
        $optionProduct = $this->createProduct('simple');

        $option = $bundleProduct->bundleOptions()->create([
            'name_en' => 'Choose Size',
            'name_ar' => 'اختر الحجم',
            'required' => true,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/v1/products/{$bundleProduct->id}/bundle-options/{$option->id}/products",
                [
                    'product_id' => $optionProduct->id,
                    'default_quantity' => 1,
                    'min_quantity' => 1,
                    'max_quantity' => 5,
                    'price_override_fils' => 15000,
                    'sort_order' => 0,
                ]
            );

        $response->assertCreated();
        $response->assertJsonPath('data.product_id', $optionProduct->id);
        $response->assertJsonPath('data.default_quantity', 1);
        $response->assertJsonPath('data.min_quantity', 1);
        $response->assertJsonPath('data.max_quantity', 5);
        $response->assertJsonPath('data.price_override_fils', 15000);
    }

    public function test_add_same_product_twice_to_bundle_option(): void
    {
        $user = User::factory()->create();
        $bundleProduct = $this->createProduct('bundle');
        $optionProduct = $this->createProduct('simple');

        $option = $bundleProduct->bundleOptions()->create([
            'name_en' => 'Choose Size',
            'name_ar' => 'اختر الحجم',
            'required' => true,
            'sort_order' => 1,
        ]);

        // First add should succeed
        $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/v1/products/{$bundleProduct->id}/bundle-options/{$option->id}/products",
                [
                    'product_id' => $optionProduct->id,
                    'default_quantity' => 1,
                ]
            )->assertCreated();

        // Second add should fail with unique constraint
        $response = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/v1/products/{$bundleProduct->id}/bundle-options/{$option->id}/products",
                [
                    'product_id' => $optionProduct->id,
                    'default_quantity' => 1,
                ]
            );

        $response->assertUnprocessable();
        $this->assertArrayHasKey('product_id', $response->json('errors'));
    }

    public function test_product_resource_shows_product_type(): void
    {
        $product = $this->createProduct('bundle');

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('data.product_type', 'bundle');
    }

    public function test_product_resource_includes_bundle_options_when_loaded(): void
    {
        $product = $this->createProduct('bundle');
        $user = User::factory()->create();

        // Create a bundle option
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bundle-options", [
                'name_en' => 'Choose Size',
                'name_ar' => 'اختر الحجم',
                'required' => true,
                'sort_order' => 1,
            ])->assertCreated();

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
        $this->assertArrayHasKey('bundle_options', $response->json('data'));
        $this->assertCount(1, $response->json('data.bundle_options'));
    }

    public function test_product_resource_includes_downloadable_links_when_loaded(): void
    {
        $product = $this->createProduct('downloadable');
        $user = User::factory()->create();

        // Create a downloadable link
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/downloadable-links", [
                'name_en' => 'Ebook PDF',
                'name_ar' => 'كتاب إلكتروني',
                'file_path' => 'downloads/ebook.pdf',
                'downloads_allowed' => 3,
                'sort_order' => 0,
            ])->assertCreated();

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
        $this->assertArrayHasKey('downloadable_links', $response->json('data'));
        $this->assertCount(1, $response->json('data.downloadable_links'));
    }

    // -----------------------------------------------------------------------
    // 3. Downloadable link CRUD
    // -----------------------------------------------------------------------

    public function test_create_downloadable_link_on_downloadable_product(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('downloadable');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/downloadable-links", [
                'name_en' => 'Ebook PDF',
                'name_ar' => 'كتاب إلكتروني',
                'file_path' => 'downloads/ebook.pdf',
                'downloads_allowed' => 3,
                'sort_order' => 0,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name_en', 'Ebook PDF');
        $response->assertJsonPath('data.name_ar', 'كتاب إلكتروني');
        $response->assertJsonPath('data.downloads_allowed', 3);
        $response->assertJsonPath('data.sort_order', 0);
    }

    public function test_create_downloadable_link_on_non_downloadable_product(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('simple');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/downloadable-links", [
                'name_en' => 'Ebook PDF',
                'name_ar' => 'كتاب إلكتروني',
                'file_path' => 'downloads/ebook.pdf',
                'downloads_allowed' => 3,
                'sort_order' => 0,
            ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('product_type', $response->json('errors'));
    }

    public function test_create_downloadable_link_requires_name_en(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('downloadable');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/downloadable-links", [
                'name_ar' => 'كتاب إلكتروني',
                'file_path' => 'downloads/ebook.pdf',
                'downloads_allowed' => 3,
                'sort_order' => 0,
            ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('name_en', $response->json('errors'));
    }

    public function test_create_downloadable_link_requires_name_ar(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('downloadable');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/downloadable-links", [
                'name_en' => 'Ebook PDF',
                'file_path' => 'downloads/ebook.pdf',
                'downloads_allowed' => 3,
                'sort_order' => 0,
            ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('name_ar', $response->json('errors'));
    }

    public function test_create_downloadable_link_requires_file_path(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('downloadable');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/downloadable-links", [
                'name_en' => 'Ebook PDF',
                'name_ar' => 'كتاب إلكتروني',
                'downloads_allowed' => 3,
                'sort_order' => 0,
            ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('file_path', $response->json('errors'));
    }

    public function test_downloadable_link_resource_does_not_expose_file_path(): void
    {
        $user = User::factory()->create();
        $product = $this->createProduct('downloadable');

        $product->downloadableLinks()->create([
            'name_en' => 'Ebook PDF',
            'name_ar' => 'كتاب إلكتروني',
            'file_path' => 'downloads/ebook.pdf',
            'downloads_allowed' => 3,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/downloadable-links", [
                'name_en' => 'Another Ebook',
                'name_ar' => 'كتاب آخر',
                'file_path' => 'downloads/another.pdf',
                'downloads_allowed' => 5,
                'sort_order' => 1,
            ]);

        $response->assertCreated();
        $this->assertArrayNotHasKey('file_path', $response->json('data'));
    }

    public function test_product_resource_shows_correct_type_for_downloadable(): void
    {
        $product = $this->createProduct('downloadable');

        $product->downloadableLinks()->create([
            'name_en' => 'Ebook PDF',
            'name_ar' => 'كتاب إلكتروني',
            'file_path' => 'downloads/ebook.pdf',
            'downloads_allowed' => 3,
            'sort_order' => 0,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonPath('data.product_type', 'downloadable');
    }

    // -----------------------------------------------------------------------
    // 4. ProductService type guards
    // -----------------------------------------------------------------------

    public function test_create_bundle_option_throws_on_non_bundle_product(): void
    {
        $product = $this->createProduct('simple');
        $service = app(ProductService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product must be of type "bundle" to add bundle options.');

        $service->createBundleOption($product, [
            'name_en' => 'Option',
            'name_ar' => 'خيار',
        ]);
    }

    public function test_create_downloadable_link_throws_on_non_downloadable_product(): void
    {
        $product = $this->createProduct('simple');
        $service = app(ProductService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product must be of type "downloadable" to add downloadable links.');

        $service->createDownloadableLink($product, [
            'name_en' => 'Link',
            'name_ar' => 'رابط',
            'file_path' => 'test.pdf',
        ]);
    }
}
