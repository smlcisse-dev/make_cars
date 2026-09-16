<?php

namespace Database\Factories;

use App\Enums\QuoteDocumentType;
use App\Enums\QuoteVersionDecision;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteVersion>
 */
class QuoteVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'version' => 1,
            'document_type' => QuoteDocumentType::Quote,
        ];
    }

    public function forQuote(Quote $quote, ?int $version = null): static
    {
        return $this->state(fn (array $attributes) => [
            'quote_id' => $quote->id,
            'version' => $version ?? ($quote->versions()->max('version') + 1),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'pdf_disk' => 'local',
            'pdf_path' => 'quotes/test/v'.($attributes['version'] ?? 1).'.pdf',
            'sent_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => QuoteVersionDecision::Accepted,
            'decided_at' => now(),
            'decided_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'decision' => QuoteVersionDecision::Rejected,
            'decided_at' => now(),
            'decided_by' => User::factory(),
        ]);
    }

    public function invoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => QuoteDocumentType::Invoice,
        ]);
    }
}
