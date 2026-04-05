<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\InventoryMovement;

class InventoryMovementService
{
    public function record(
        int $variantId,
        string $type,
        int $delta,
        int $quantityAfter,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $createdBy = null,
    ): InventoryMovement {
        return InventoryMovement::create([
            'variant_id' => $variantId,
            'type' => $type,
            'quantity_delta' => $delta,
            'quantity_after' => $quantityAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);
    }
}
