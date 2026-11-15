<?php

namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\BankAccount;
use App\Models\Shop;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Note: this factory does not touch balances. Use WithdrawalService::request() when balances matter.
 *
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'bank_account_id' => fn (array $attributes) => BankAccount::factory()->create(['shop_id' => $attributes['shop_id']])->id,
            'amount' => '1000.00',
            'status' => WithdrawalStatus::Pending,
        ];
    }
}
