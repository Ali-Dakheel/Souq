<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cart Configuration
    |--------------------------------------------------------------------------
    | These values can be overridden per client. Admin-configurable rules
    | are read from this config; future versions will persist in the DB.
    */

    // Maximum quantity allowed per cart item (also capped at available stock)
    'max_quantity_per_item' => (int) env('CART_MAX_QUANTITY', 10),

    // Guest cart TTL in days (applies to carts without a user_id)
    'guest_ttl_days' => (int) env('CART_GUEST_TTL_DAYS', 30),

    // VAT rate as a decimal (10% = 0.10)
    'vat_rate' => 0.10,
];
