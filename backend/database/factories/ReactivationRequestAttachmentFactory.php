<?php

namespace Database\Factories;

use App\Models\ReactivationRequest;
use App\Models\ReactivationRequestAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReactivationRequestAttachment>
 */
class ReactivationRequestAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reactivation_request_id' => ReactivationRequest::factory(),
            'disk' => 'local',
            'path' => 'reactivation-request-attachments/'.fake()->uuid().'.pdf',
            'original_name' => 'preuve.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
