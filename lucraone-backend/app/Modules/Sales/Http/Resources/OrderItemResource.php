<?php

namespace App\Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'product_id' => $this->product_id,
            'sku' => $this->sku,
            'name' => $this->name,
            'unit' => $this->unit,
            'sale_presentation_type' => $this->sale_presentation_type,
            'product_package_id' => $this->product_package_id,
            'package_name' => $this->package_name,
            'package_factor' => $this->package_factor,
            'presentation_barcode' => $this->presentation_barcode,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total' => $this->total,
        ];
    }
}
