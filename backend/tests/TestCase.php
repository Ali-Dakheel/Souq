<?php

namespace Tests;

use Database\Seeders\StoreSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed store settings (including last_invoice_sequence) after each
        // RefreshDatabase reset so listeners that generate invoices can run
        // without RuntimeException.
        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed(StoreSettingsSeeder::class);
        }
    }
}
