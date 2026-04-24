<?php

declare(strict_types=1);

namespace App\Modules\Orders\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $order_id
 * @property string $invoice_number
 * @property int $subtotal_fils
 * @property int $vat_fils
 * @property int $discount_fils
 * @property int $total_fils
 * @property string|null $cr_number
 * @property string|null $vat_number
 * @property string|null $company_name_en
 * @property string|null $company_name_ar
 * @property string|null $company_address_en
 * @property string|null $company_address_ar
 * @property Carbon $issued_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
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

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
