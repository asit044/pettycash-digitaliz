<?php

namespace Database\Factories;

use App\Models\BudgetCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetCode>
 */
class BudgetCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('??-###')),
            'description' => fake()->sentence(3),
            'is_active' => true,
        ];
    }
}
