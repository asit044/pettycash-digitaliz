<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PettyCashRequest>
 */
class PettyCashRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_number' => 'KC-'.now()->format('Y').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'requester_id' => User::factory(),
            'nominal' => fake()->numberBetween(50000, 5000000),
            'currency' => 'IDR',
            'description' => fake()->sentence(),
            'status' => RequestStatus::PendingReview->value,
            'submitted_at' => now(),
        ];
    }

    public function needsRevision(): static
    {
        return $this->state(fn () => ['status' => RequestStatus::NeedsRevision->value]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => RequestStatus::Rejected->value]);
    }

    public function processing(): static
    {
        return $this->state(fn () => [
            'status' => RequestStatus::Processing->value,
            'budget_code' => 'OPR-001',
            'budget_description' => 'Operasional',
            'reviewed_at' => now(),
        ]);
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => RequestStatus::Done->value,
            'budget_code' => 'OPR-001',
            'budget_description' => 'Operasional',
            'reviewed_at' => now(),
            'paid_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
