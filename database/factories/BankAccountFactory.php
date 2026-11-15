<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    public function definition(): array
    {
        $number = fake()->numerify('############');

        return [
            'shop_id' => Shop::factory(),
            'bank_name' => 'BDO',
            'account_name' => fake()->name(),
            'account_number' => $number,
            'account_last4' => substr($number, -4),
            'is_default' => true,
        ];
    }
}
