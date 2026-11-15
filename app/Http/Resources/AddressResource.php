<?php

namespace App\Http\Resources;

use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserAddress
 */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource->only(['id', 'label', 'recipient_name', 'phone', 'region', 'province', 'city', 'barangay', 'street', 'postal_code', 'is_default']);
    }
}
