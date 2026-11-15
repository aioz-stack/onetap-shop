<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Enums\SellerStatus;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SellerProfile>
 */
class SellerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'business_name' => fake()->company(),
            'business_type' => 'sole_proprietor',
            'tin' => fake()->numerify('###-###-###-000'),
            'status' => SellerStatus::Pending,
        ];
    }

    /**
     * An approved seller with the seller role.
     */
    public function approved(): static
    {
        return $this->state(['status' => SellerStatus::Approved, 'approved_at' => now()])
            ->afterCreating(fn (SellerProfile $profile) => $profile->user->assignRole(RoleName::Seller));
    }
}
