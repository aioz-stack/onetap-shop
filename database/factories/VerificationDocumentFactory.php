<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\User;
use App\Models\VerificationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationDocument>
 */
class VerificationDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'type' => 'government_id',
            'path' => 'verification/'.fake()->uuid().'.jpg',
            'status' => VerificationStatus::Pending,
        ];
    }
}
