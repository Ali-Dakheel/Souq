<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompareTest extends TestCase
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

    private function createProduct(array $data = []): Product
    {
        return Product::create(array_merge([
            'name' => ['ar' => 'منتج', 'en' => 'Product'],
            'slug' => 'prod-'.uniqid(),
            'category_id' => $this->createCategory()->id,
            'base_price_fils' => 5000,
            'is_available' => true,
            'product_type' => 'simple',
        ], $data));
    }

    private function createVariant(Product $product, array $attributes = []): Variant
    {
        return Variant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'is_available' => true,
            'attributes' => $attributes,
        ]);
    }

    // -----------------------------------------------------------------------
    // Test 1: POST /compare with 2 variant IDs returns 200
    // -----------------------------------------------------------------------

    public function test_compare_with_two_variants_returns_200(): void
    {
        $product1 = $this->createProduct(['name' => ['ar' => 'تيشيرت أزرق', 'en' => 'Blue T-Shirt']]);
        $product2 = $this->createProduct(['name' => ['ar' => 'تيشيرت أحمر', 'en' => 'Red T-Shirt']]);

        $variant1 = $this->createVariant($product1, ['color' => 'blue', 'size' => 'M']);
        $variant2 = $this->createVariant($product2, ['color' => 'red', 'size' => 'L']);

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant1->id, $variant2->id],
        ]);

        $response->assertOk();
    }

    // -----------------------------------------------------------------------
    // Test 2: Response has data.products array with correct count
    // -----------------------------------------------------------------------

    public function test_response_has_products_array_with_correct_count(): void
    {
        $product1 = $this->createProduct(['name' => ['ar' => 'تيشيرت', 'en' => 'T-Shirt']]);
        $product2 = $this->createProduct(['name' => ['ar' => 'بنطال', 'en' => 'Pants']]);
        $product3 = $this->createProduct(['name' => ['ar' => 'حذاء', 'en' => 'Shoe']]);

        $variant1 = $this->createVariant($product1, ['color' => 'blue']);
        $variant2 = $this->createVariant($product2, ['color' => 'black']);
        $variant3 = $this->createVariant($product3, ['color' => 'white']);

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant1->id, $variant2->id, $variant3->id],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['products']]);
        $this->assertCount(3, $response->json('data.products'));
    }

    // -----------------------------------------------------------------------
    // Test 3: Response has data.attributes with unified keys
    // -----------------------------------------------------------------------

    public function test_response_has_attributes_with_unified_keys(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $variant1 = $this->createVariant($product1, ['color' => 'blue', 'size' => 'M']);
        $variant2 = $this->createVariant($product2, ['color' => 'red', 'material' => 'cotton']);

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant1->id, $variant2->id],
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['attributes']]);

        $attributes = $response->json('data.attributes');
        $this->assertArrayHasKey('color', $attributes);
        $this->assertArrayHasKey('size', $attributes);
        $this->assertArrayHasKey('material', $attributes);
    }

    // -----------------------------------------------------------------------
    // Test 4: Attribute values are null where variant doesn't have the attribute
    // -----------------------------------------------------------------------

    public function test_attribute_values_are_null_when_missing(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $variant1 = $this->createVariant($product1, ['color' => 'blue', 'size' => 'M']);
        $variant2 = $this->createVariant($product2, ['color' => 'red']); // missing size

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant1->id, $variant2->id],
        ]);

        $response->assertOk();
        $attributes = $response->json('data.attributes');

        // color should have values for both
        $this->assertEquals(['blue', 'red'], $attributes['color']);

        // size should have value for first, null for second
        $this->assertEquals(['M', null], $attributes['size']);
    }

    // -----------------------------------------------------------------------
    // Test 5: Input order is preserved in products array
    // -----------------------------------------------------------------------

    public function test_input_order_is_preserved(): void
    {
        $product1 = $this->createProduct(['name' => ['ar' => 'منتج 1', 'en' => 'Product 1']]);
        $product2 = $this->createProduct(['name' => ['ar' => 'منتج 2', 'en' => 'Product 2']]);
        $product3 = $this->createProduct(['name' => ['ar' => 'منتج 3', 'en' => 'Product 3']]);

        $variant1 = $this->createVariant($product1);
        $variant2 = $this->createVariant($product2);
        $variant3 = $this->createVariant($product3);

        // Request in reverse order: 3, 2, 1
        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant3->id, $variant2->id, $variant1->id],
        ]);

        $response->assertOk();
        $products = $response->json('data.products');

        // Should maintain input order
        $this->assertEquals($product3->id, $products[0]['id']);
        $this->assertEquals($product2->id, $products[1]['id']);
        $this->assertEquals($product1->id, $products[2]['id']);
    }

    // -----------------------------------------------------------------------
    // Test 6: POST /compare with 4 variant IDs (max) succeeds
    // -----------------------------------------------------------------------

    public function test_compare_with_four_variants_max_succeeds(): void
    {
        $products = collect(range(1, 4))->map(fn () => $this->createProduct());
        $variants = $products->map(fn ($p) => $this->createVariant($p));

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => $variants->pluck('id')->toArray(),
        ]);

        $response->assertOk();
        $this->assertCount(4, $response->json('data.products'));
    }

    // -----------------------------------------------------------------------
    // Test 7: POST /compare with 5 variant IDs returns 422 (max 4)
    // -----------------------------------------------------------------------

    public function test_compare_with_five_variants_returns_422(): void
    {
        $products = collect(range(1, 5))->map(fn () => $this->createProduct());
        $variants = $products->map(fn ($p) => $this->createVariant($p));

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => $variants->pluck('id')->toArray(),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('variant_ids');
    }

    // -----------------------------------------------------------------------
    // Test 8: POST /compare with empty array returns 422
    // -----------------------------------------------------------------------

    public function test_compare_with_empty_array_returns_422(): void
    {
        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('variant_ids');
    }

    // -----------------------------------------------------------------------
    // Test 9: POST /compare with non-existent variant ID returns 422
    // -----------------------------------------------------------------------

    public function test_compare_with_nonexistent_variant_returns_422(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product);

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant->id, 99999],
        ]);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('variant_ids.1', $response->json('errors'));
    }

    // -----------------------------------------------------------------------
    // Test 10: POST /compare with 1 variant returns correct single-item matrix
    // -----------------------------------------------------------------------

    public function test_compare_with_single_variant(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product, ['color' => 'blue', 'size' => 'M']);

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant->id],
        ]);

        $response->assertOk();

        $products = $response->json('data.products');
        $this->assertCount(1, $products);
        $this->assertEquals($variant->id, $products[0]['variant']['id']);

        $attributes = $response->json('data.attributes');
        $this->assertEquals([['blue']], array_map(fn ($val) => (array) $val, $attributes['color']));
    }

    // -----------------------------------------------------------------------
    // Test 11: Variants from different products are compared correctly
    // -----------------------------------------------------------------------

    public function test_variants_from_different_products(): void
    {
        $product1 = $this->createProduct([
            'name' => ['ar' => 'تيشيرت', 'en' => 'T-Shirt'],
            'base_price_fils' => 5000,
        ]);
        $product2 = $this->createProduct([
            'name' => ['ar' => 'بنطال', 'en' => 'Pants'],
            'base_price_fils' => 10000,
        ]);

        $variant1 = $this->createVariant($product1, ['color' => 'blue']);
        $variant2 = $this->createVariant($product2, ['color' => 'black']);

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant1->id, $variant2->id],
        ]);

        $response->assertOk();

        $products = $response->json('data.products');
        $this->assertEquals($product1->id, $products[0]['id']);
        $this->assertEquals('T-Shirt', $products[0]['name_en']);
        $this->assertEquals($product2->id, $products[1]['id']);
        $this->assertEquals('Pants', $products[1]['name_en']);
    }

    // -----------------------------------------------------------------------
    // Test 12: No authentication required (public endpoint)
    // -----------------------------------------------------------------------

    public function test_endpoint_is_public(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product);

        // Not logged in, but request should still succeed
        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant->id],
        ]);

        $response->assertOk();
    }

    // -----------------------------------------------------------------------
    // Test 13: Variant SKU is included in response
    // -----------------------------------------------------------------------

    public function test_variant_sku_is_included(): void
    {
        $product = $this->createProduct();
        $variant = $this->createVariant($product);

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant->id],
        ]);

        $response->assertOk();
        $products = $response->json('data.products');
        $this->assertEquals($variant->sku, $products[0]['variant']['sku']);
    }

    // -----------------------------------------------------------------------
    // Test 14: Variant price_fils is included and uses effective_price_fils
    // -----------------------------------------------------------------------

    public function test_variant_price_fils_uses_effective_price(): void
    {
        $product = $this->createProduct(['base_price_fils' => 5000]);
        // Variant with no override price_fils will use product's base_price_fils
        $variant = $this->createVariant($product, ['color' => 'blue']);

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant->id],
        ]);

        $response->assertOk();
        $products = $response->json('data.products');
        $this->assertEquals(5000, $products[0]['variant']['price_fils']);
    }

    // -----------------------------------------------------------------------
    // Test 15: Product names are included with both en and ar keys
    // -----------------------------------------------------------------------

    public function test_product_names_include_both_languages(): void
    {
        $product = $this->createProduct([
            'name' => ['ar' => 'تيشيرت أزرق', 'en' => 'Blue T-Shirt'],
        ]);
        $variant = $this->createVariant($product);

        $response = $this->postJson('/api/v1/compare', [
            'variant_ids' => [$variant->id],
        ]);

        $response->assertOk();
        $products = $response->json('data.products');
        $this->assertEquals('Blue T-Shirt', $products[0]['name_en']);
        $this->assertEquals('تيشيرت أزرق', $products[0]['name_ar']);
    }
}
