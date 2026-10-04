<?php

namespace App\Modules\Products\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'product_id' => (string) $this->product_id,
            'currency' => $this->currency,
            'amount' => (float) $this->amount,
            'type' => $this->type,
            'reference_cost_amount' => $this->reference_cost_amount,
            'effective_margin_percentage' => $this->effective_margin_percentage,
            'margin_percentage' => $this->when(
                $this->type === 'sale',
                fn () => $this->effective_margin_percentage === null ? null : (float) $this->effective_margin_percentage
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
