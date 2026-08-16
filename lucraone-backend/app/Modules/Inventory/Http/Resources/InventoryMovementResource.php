<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'inventory_id' => $this->inventory_id,
            'product_id' => $this->product_id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'quantity' => $this->quantity,
            'quantity_before' => $this->quantity_before,
            'quantity_after' => $this->quantity_after,
            'reason' => $this->reason,
            'moved_at' => $this->moved_at,
            'user' => $this->whenLoaded('user'),
            'product' => $this->whenLoaded('product'),
            'company' => $this->whenLoaded('company'),
        ];
    }
}
