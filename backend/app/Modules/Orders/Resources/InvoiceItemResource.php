<?php

declare(strict_types=1);

namespace App\Modules\Orders\Resources;

use App\Modules\Orders\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceItem */
class InvoiceItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'variant_id' => $this->variant_id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price_fils' => $this->unit_price_fils,
            'vat_rate' => $this->vat_rate, // integer percentage: 10 means 10% VAT
            'vat_fils' => $this->vat_fils,
            'total_fils' => $this->total_fils,
        ];
    }
}
