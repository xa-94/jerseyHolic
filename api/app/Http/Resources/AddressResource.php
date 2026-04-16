<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'firstname'    => $this->firstname,
            'lastname'     => $this->lastname,
            'full_name'    => $this->full_name,
            'company'      => $this->company,
            'address_1'    => $this->address_1,
            'address_2'    => $this->address_2,
            'city'         => $this->city,
            'postcode'     => $this->postcode,
            'country_id'   => $this->country_id,
            'country_name' => $this->country_name,
            'zone_id'      => $this->zone_id,
            'zone_name'    => $this->zone_name,
            'phone'        => $this->phone,
            'is_default'   => (bool) $this->is_default,
        ];
    }
}
