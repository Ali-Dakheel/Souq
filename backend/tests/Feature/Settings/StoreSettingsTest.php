<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\StoreSetting;
use App\Modules\Settings\Services\StoreSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private StoreSettingsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StoreSettingsService::class);
    }

    // -----------------------------------------------------------------------
    // get() method
    // -----------------------------------------------------------------------

    public function test_get_returns_seeded_value(): void
    {
        $value = $this->service->get('cr_number');

        $this->assertNotNull($value);
        $this->assertEquals('CR-00000000', $value);
    }

    public function test_get_returns_default_when_key_missing(): void
    {
        $value = $this->service->get('nonexistent_key', 'my-default');

        $this->assertEquals('my-default', $value);
    }

    public function test_get_returns_null_when_key_missing_and_no_default(): void
    {
        $value = $this->service->get('nonexistent_key');

        $this->assertNull($value);
    }

    // -----------------------------------------------------------------------
    // getGroup() method
    // -----------------------------------------------------------------------

    public function test_get_group_returns_only_keys_in_that_group(): void
    {
        StoreSetting::create(['key' => 'legal_1', 'value' => 'value1', 'group' => 'legal']);
        StoreSetting::create(['key' => 'legal_2', 'value' => 'value2', 'group' => 'legal']);
        StoreSetting::create(['key' => 'branding_1', 'value' => 'value3', 'group' => 'branding']);

        // Flush cache to pick up new settings
        $this->service->flush();

        $legalSettings = $this->service->getGroup('legal');

        // Should have at least our two legal settings
        $this->assertArrayHasKey('legal_1', $legalSettings);
        $this->assertArrayHasKey('legal_2', $legalSettings);
        $this->assertArrayNotHasKey('branding_1', $legalSettings);
        $this->assertEquals('value1', $legalSettings['legal_1']);
        $this->assertEquals('value2', $legalSettings['legal_2']);
    }

    public function test_get_group_returns_empty_array_for_nonexistent_group(): void
    {
        $result = $this->service->getGroup('nonexistent_group');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // -----------------------------------------------------------------------
    // bulkUpdate() method
    // -----------------------------------------------------------------------

    public function test_bulk_update_updates_allowed_keys(): void
    {
        // The seeder creates cr_number and vat_number, update those instead
        $this->service->bulkUpdate([
            'cr_number' => 'CR-12345678',
        ]);

        $this->service->flush();
        $updated = $this->service->get('cr_number');
        $this->assertEquals('CR-12345678', $updated);
    }

    public function test_bulk_update_ignores_disallowed_keys(): void
    {
        // last_invoice_sequence is NOT in EDITABLE_KEYS
        // The seeder creates it with value 0
        // Try to update it (should be silently ignored)
        $this->service->bulkUpdate([
            'last_invoice_sequence' => '999',
        ]);

        // Should NOT have been updated (silently ignored)
        $value = $this->service->get('last_invoice_sequence');
        $this->assertEquals('0', $value); // should still be seeded value
    }

    public function test_bulk_update_filters_mixed_allowed_and_disallowed(): void
    {
        // Delete and recreate to avoid unique constraint issues
        StoreSetting::where('key', 'company_name_en')->delete();
        StoreSetting::create(['key' => 'company_name_en', 'value' => 'Old', 'group' => 'general']);

        $this->service->flush(); // flush cache before updating

        $this->service->bulkUpdate([
            'company_name_en' => 'New',
            'last_invoice_sequence' => '999',
        ]);

        // Only the allowed key should be updated
        $this->assertEquals('New', $this->service->get('company_name_en'));
        $this->assertEquals('0', $this->service->get('last_invoice_sequence')); // should still be original seeded value
    }

    public function test_bulk_update_only_updates_pre_existing_keys(): void
    {
        // Create a key that definitely doesn't exist
        StoreSetting::where('key', 'nonexistent_editable_key')->delete();

        $this->service->bulkUpdate([
            'nonexistent_editable_key' => 'some-value', // would be editable if existed
        ]);

        // The service uses StoreSetting::where(...)->update(), which doesn't
        // create new rows — it only updates existing ones.
        $this->assertDatabaseMissing('store_settings', [
            'key' => 'nonexistent_editable_key',
        ]);
    }

    // -----------------------------------------------------------------------
    // getNextInvoiceSequence() method
    // -----------------------------------------------------------------------

    public function test_get_next_invoice_sequence_increments_atomically(): void
    {
        // The seeder creates last_invoice_sequence = 0
        $seq1 = $this->service->getNextInvoiceSequence();
        $seq2 = $this->service->getNextInvoiceSequence();
        $seq3 = $this->service->getNextInvoiceSequence();

        $this->assertEquals(1, $seq1);
        $this->assertEquals(2, $seq2);
        $this->assertEquals(3, $seq3);
    }

    public function test_get_next_invoice_sequence_persists_across_calls(): void
    {
        $seq1 = $this->service->getNextInvoiceSequence();

        // Create a fresh instance of the service (new cache)
        $freshService = app(StoreSettingsService::class);
        $seq2 = $freshService->getNextInvoiceSequence();

        $this->assertEquals(1, $seq1);
        $this->assertEquals(2, $seq2);
    }

    public function test_get_next_invoice_sequence_throws_when_row_missing(): void
    {
        // Delete the last_invoice_sequence row
        StoreSetting::where('key', 'last_invoice_sequence')->delete();
        $this->service->flush();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('last_invoice_sequence setting is missing');

        $this->service->getNextInvoiceSequence();
    }

    // -----------------------------------------------------------------------
    // Cache behavior
    // -----------------------------------------------------------------------

    public function test_in_memory_cache_is_loaded_once_per_request(): void
    {
        // First call loads all settings into cache
        $val1 = $this->service->get('cr_number');

        // Add a new setting to the DB (simulating concurrent change)
        StoreSetting::create(['key' => 'new_key', 'value' => 'new_value', 'group' => 'test']);

        // Without flushing, the cache should still return the old state
        // (no new_key visible)
        $val2 = $this->service->get('new_key');
        $this->assertNull($val2);

        // After flush, the cache is reloaded and new_key is visible
        $this->service->flush();
        $val3 = $this->service->get('new_key');
        $this->assertEquals('new_value', $val3);
    }

    public function test_cache_format_stores_value_and_group(): void
    {
        // Create a setting with specific group
        StoreSetting::create(['key' => 'test_key', 'value' => 'test_value', 'group' => 'custom_group']);
        $this->service->flush();

        // Retrieve via getGroup to ensure the internal cache stores both value and group
        $group = $this->service->getGroup('custom_group');
        $this->assertArrayHasKey('test_key', $group);
        $this->assertEquals('test_value', $group['test_key']);
    }
}
