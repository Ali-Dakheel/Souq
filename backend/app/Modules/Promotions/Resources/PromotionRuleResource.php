<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Resources;

use App\Modules\Promotions\Models\PromotionRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PromotionRule */
class PromotionRuleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'actions' => $this->whenLoaded('actions', fn () => $this->actions->map(fn ($a) => ['type' => $a->type, 'value' => $a->value])
            ),
        ];
    }
}
