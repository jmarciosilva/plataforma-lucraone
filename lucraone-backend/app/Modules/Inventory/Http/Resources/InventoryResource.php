<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'company_id' => $this->company_id,
            'quantity_on_hand' => $this->quantity_on_hand,
            'reserved' => $this->reserved,
            'available' => $this->available,
            'product' => $this->whenLoaded('product'),
            'company' => $this->whenLoaded('company'),
            'stock_level' => $this->whenLoaded('stockLevel'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
