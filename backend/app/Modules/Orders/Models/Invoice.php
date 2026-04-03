<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = [
        'order_id',
        'invoice_number',
        'subtotal_fils',
        'vat_fils',
        'discount_fils',
        'total_fils',
        'cr_number',
        'vat_number',
        'company_name_en',
        'company_name_ar',
        'company_address_en',
        'company_address_ar',
        'issued_at',
    ];

    protected $casts = [
        'subtotal_fils' => 'integer',
        'vat_fils' => 'integer',
        'discount_fils' => 'integer',
        'total_fils' => 'integer',
        'issued_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
