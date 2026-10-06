<?php

namespace App\Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'unit' => $this->unit,
            'name' => $this->name,
            'description' => $this->description,
            'ncm_code' => $this->ncm_code,
            'cest_code' => $this->cest_code,
            'default_origin_code' => $this->default_origin_code,
            'status' => $this->status,
            'company_id' => $this->company_id,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'prices' => PriceResource::collection($this->whenLoaded('prices')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
