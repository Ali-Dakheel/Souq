<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Modules\Settings\Models\StoreSetting;
use Illuminate\Support\Facades\DB;

class StoreSettingsService
{
    private const EDITABLE_KEYS = [
        'cr_number', 'vat_number', 'company_name_en', 'company_name_ar',
        'company_address_en', 'company_address_ar', 'logo_path', 'favicon_path',
        'support_email', 'support_phone',
    ];

    /** @var array<string, array{value: string|null, group: string}>|null */
    private ?array $cache = null;

    /**
     * Get a setting value by key, with optional default.
     * Loads all settings once per request lifecycle (in-memory cache).
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $this->loadCache();

        return $this->cache[$key]['value'] ?? $default;
    }

    /**
     * Get all settings for a given group as key => value array.
     *
     * @return array<string, string|null>
     */
    public function getGroup(string $group): array
    {
        $this->loadCache();

        $result = [];
        foreach ($this->cache as $key => $data) {
            if ($data['group'] === $group) {
                $result[$key] = $data['value'];
            }
        }

        return $result;
    }

    /**
     * Set a setting value. Creates if missing, updates if exists.
     * Flushes the in-memory cache.
     */
    public function set(string $key, ?string $value, string $group = 'general'): void
    {
        StoreSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'group' => $group],
        );

        $this->flush();
    }

    /**
     * Bulk update multiple settings at once.
     * Only accepts keys in the EDITABLE_KEYS allowlist — ignores all others.
     * Wraps in a DB transaction. Only updates pre-existing keys.
     *
     * @param  array<string, string|null>  $settings
     */
    public function bulkUpdate(array $settings): void
    {
        $filtered = array_filter(
            $settings,
            fn (string $key) => in_array($key, self::EDITABLE_KEYS, true),
            ARRAY_FILTER_USE_KEY
        );

        DB::transaction(function () use ($filtered): void {
            foreach ($filtered as $key => $value) {
                StoreSetting::where('key', $key)->update(['value' => $value]);
            }
        });

        $this->flush();
    }

    /**
     * Get the next invoice sequence number using a locked read.
     * Returns the new sequence number (already incremented and saved).
     * Requires last_invoice_sequence to exist — run StoreSettingsSeeder first.
     */
    public function getNextInvoiceSequence(): int
    {
        return DB::transaction(function (): int {
            $row = StoreSetting::where('key', 'last_invoice_sequence')
                ->lockForUpdate()
                ->first();

            if (! $row) {
                throw new \RuntimeException('last_invoice_sequence setting is missing. Run StoreSettingsSeeder.');
            }

            $next = (int) $row->value + 1;
            $row->value = (string) $next;
            $row->save();

            return $next;
        });
    }

    /**
     * Flush the in-memory cache (for testing or after writes).
     */
    public function flush(): void
    {
        $this->cache = null;
    }

    /**
     * Load all settings into the in-memory cache if not already loaded.
     */
    private function loadCache(): void
    {
        if ($this->cache !== null) {
            return;
        }

        $this->cache = StoreSetting::all()
            ->keyBy('key')
            ->map(fn ($row) => ['value' => $row->value, 'group' => $row->group])
            ->toArray();
    }
}
