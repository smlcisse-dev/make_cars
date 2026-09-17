<?php

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\DisputeAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisputeAttachment>
 */
class DisputeAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dispute_id' => Dispute::factory(),
            'disk' => 'local',
            'path' => 'dispute-attachments/test/'.$this->faker->uuid().'.jpg',
            'position' => 0,
        ];
    }

    public function forDispute(Dispute $dispute): static
    {
        return $this->state(fn (array $attributes) => [
            'dispute_id' => $dispute->id,
        ]);
    }
}
