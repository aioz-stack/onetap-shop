<?php

namespace Database\Factories;

use App\Enums\PartnerStatus;
use App\Enums\RoleName;
use App\Models\LogisticsPartner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LogisticsPartner>
 */
class LogisticsPartnerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->withRole(RoleName::Logistics),
            'name' => fake()->company().' Express',
            'code' => Str::upper(Str::random(5)),
            'contact_phone' => '0281234567',
            'contact_email' => fake()->companyEmail(),
            'base_delivery_fee' => '59.00',
            'status' => PartnerStatus::Active,
        ];
    }
}
