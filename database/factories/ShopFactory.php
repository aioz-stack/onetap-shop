<?php

namespace Database\Factories;

use App\Enums\ShopStatus;
use App\Models\SellerProfile;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'seller_profile_id' => SellerProfile::factory()->approved(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'pickup_street' => fake()->streetAddress(),
            'pickup_barangay' => 'Bagong Pag-asa',
            'pickup_city' => 'Quezon City',
            'pickup_province' => 'Metro Manila',
            'pickup_region' => 'NCR',
            'pickup_phone' => '09181234567',
            'status' => ShopStatus::Active,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(fn (Shop $shop) => $shop->balance()->firstOrCreate([]));
    }
}
