<?php

namespace App\Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockLevelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'product_id' => $this->product_id,
            'company_id' => $this->company_id,
            'min_qty' => $this->min_qty,
            'max_qty' => $this->max_qty,
            'reorder_point' => $this->reorder_point,
            'product' => $this->whenLoaded('product'),
            'company' => $this->whenLoaded('company'),
        ];
    }
}
