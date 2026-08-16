<?php

namespace App\Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'type' => $this->type,
            'margin_percentage' => $this->when(
                $this->type === 'sale',
                fn () => $this->margin_percentage
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
