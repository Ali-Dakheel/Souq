<?php

declare(strict_types=1);

namespace App\Modules\Orders\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'order_id' => $this->order_id,
            'subtotal_fils' => $this->subtotal_fils,
            'vat_fils' => $this->vat_fils,
            'discount_fils' => $this->discount_fils,
            'total_fils' => $this->total_fils,
            'cr_number' => $this->cr_number,
            'vat_number' => $this->vat_number,
            'company_name_en' => $this->company_name_en,
            'company_name_ar' => $this->company_name_ar,
            'company_address_en' => $this->company_address_en,
            'company_address_ar' => $this->company_address_ar,
            'issued_at' => $this->issued_at->toIso8601String(),
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
