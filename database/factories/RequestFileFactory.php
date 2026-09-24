<?php

namespace Database\Factories;

use App\Enums\RequestFileType;
use App\Models\PettyCashRequest;
use App\Models\RequestFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestFile>
 */
class RequestFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_id' => PettyCashRequest::factory(),
            'type' => RequestFileType::Invoice->value,
            'original_name' => 'invoice-'.fake()->word().'.pdf',
            'storage_path' => 'requests/'.fake()->uuid().'.pdf',
            'uploaded_by' => User::factory(),
        ];
    }

    public function officialReceipt(): static
    {
        return $this->state(fn () => ['type' => RequestFileType::OfficialReceipt->value]);
    }
}
