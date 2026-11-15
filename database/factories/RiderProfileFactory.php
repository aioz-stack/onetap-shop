<?php

namespace Database\Factories;

use App\Enums\RiderAvailability;
use App\Enums\RiderStatus;
use App\Enums\RoleName;
use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiderProfile>
 */
class RiderProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->withRole(RoleName::Rider),
            'logistics_partner_id' => LogisticsPartner::factory(),
            'vehicle_type' => 'motorcycle',
            'plate_number' => fake()->bothify('??? ####'),
            'status' => RiderStatus::Active,
            'availability' => RiderAvailability::Offline,
        ];
    }
}
