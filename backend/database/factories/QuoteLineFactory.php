<?php

namespace Database\Factories;

use App\Enums\QuoteLineType;
use App\Models\Product;
use App\Models\QuoteLine;
use App\Models\QuoteVersion;
use App\Models\RepairService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLine>
 */
class QuoteLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 1000, 50000);
        $quantity = 1;

        return [
            'quote_version_id' => QuoteVersion::factory(),
            'type' => QuoteLineType::DiagnosisFee,
            'label' => 'Frais de diagnostic',
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'line_total' => $unitPrice * $quantity,
        ];
    }

    public function forVersion(QuoteVersion $version): static
    {
        return $this->state(fn (array $attributes) => [
            'quote_version_id' => $version->id,
        ]);
    }

    public function forService(RepairService $service, int $quantity = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuoteLineType::Service,
            'repair_service_id' => $service->id,
            'label' => $service->name,
            'unit_price' => $service->price,
            'quantity' => $quantity,
            'line_total' => $service->price * $quantity,
        ]);
    }

    public function forProduct(Product $product, int $quantity = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuoteLineType::Product,
            'product_id' => $product->id,
            'label' => $product->name,
            'unit_price' => $product->price,
            'quantity' => $quantity,
            'line_total' => $product->price * $quantity,
        ]);
    }
}
