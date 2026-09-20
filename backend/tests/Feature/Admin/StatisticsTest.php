<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Models\Appointment;
use App\Models\Department;
use App\Models\Dispute;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Statistiques agrégées pour les autorités béninoises (CLAUDE.md §1, ajout
 * v0.17).
 */
class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_a_non_admin_cannot_view_statistics(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/statistics')->assertForbidden();
    }

    public function test_it_counts_structures_by_type_and_status(): void
    {
        ProfessionalRegistration::factory()->approved()->create();
        ProfessionalRegistration::factory()->suspended()->create();
        ProfessionalRegistration::factory()->create();
        ProfessionalRegistration::factory()->rejected()->create();
        ProfessionalRegistration::factory()->marketSpace()->approved()->create();
        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/statistics');

        $response->assertOk();
        $response->assertJsonPath('data.structures.'.AccountType::Garagiste->value.'.approved', 1);
        $response->assertJsonPath('data.structures.'.AccountType::Garagiste->value.'.suspended', 1);
        $response->assertJsonPath('data.structures.'.AccountType::Garagiste->value.'.pending', 1);
        $response->assertJsonPath('data.structures.'.AccountType::Garagiste->value.'.rejected', 1);
        $response->assertJsonPath('data.structures.'.AccountType::Garagiste->value.'.total', 4);
        $response->assertJsonPath('data.structures.'.AccountType::MarketSpace->value.'.approved', 1);
    }

    public function test_it_breaks_down_geography_by_department_separately_for_garages_and_market_space(): void
    {
        $littoral = Department::where('slug', 'littoral')->firstOrFail();
        $borgou = Department::where('slug', 'borgou')->firstOrFail();

        Garage::factory()->count(2)->create(['department_id' => $littoral->id]);
        Garage::factory()->create(['department_id' => null]);
        MarketSpaceAccount::factory()->create(['department_id' => $borgou->id]);
        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/statistics');

        $response->assertOk();
        $garages = collect($response->json('data.geography.'.AccountType::Garagiste->value));
        $marketSpace = collect($response->json('data.geography.'.AccountType::MarketSpace->value));

        $this->assertSame(2, $garages->firstWhere('department_id', $littoral->id)['count']);
        $this->assertNull($garages->firstWhere('department_id', $borgou->id));
        $this->assertSame(1, $garages->firstWhere('department_id', null)['count']);
        $this->assertNull($garages->last()['department_id']);
        $this->assertSame(1, $marketSpace->firstWhere('department_id', $borgou->id)['count']);
        $this->assertCount(1, $marketSpace);
    }

    public function test_it_reports_activity_volume_within_a_period(): void
    {
        $garage = Garage::factory()->create();

        Appointment::factory()->forGarage($garage)->create(['created_at' => '2026-01-15']);
        Appointment::factory()->forGarage($garage)->create(['created_at' => '2026-03-01']);

        Quote::factory()->forGarage($garage)->sent()->create(['created_at' => '2026-01-20']);

        $invoicedQuote = Quote::factory()->forGarage($garage)->invoiced()->create(['paid_at' => '2026-01-25', 'created_at' => '2026-01-10']);
        $invoiceVersion = QuoteVersion::factory()->forQuote($invoicedQuote, 1)->invoice()->create();
        $invoiceVersion->lines()->create(['type' => 'diagnosis_fee', 'label' => 'Diagnostic', 'unit_price' => 5000, 'quantity' => 1, 'line_total' => 5000]);

        $product = Product::factory()->forGarage($garage)->approved()->create(['price' => 2000]);
        $order = Order::factory()->forGarage($garage)->create(['status' => OrderStatus::Paid, 'paid_at' => '2026-01-28', 'created_at' => '2026-01-05']);
        $order->lines()->create(['product_id' => $product->id, 'label' => $product->name, 'unit_price' => 2000, 'quantity' => 1, 'line_total' => 2000]);

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/statistics?start_date=2026-01-01&end_date=2026-01-31');

        $response->assertOk();
        $response->assertJsonPath('data.activity.appointments_count', 1);
        $response->assertJsonPath('data.activity.quotes_issued_count', 2);
        $response->assertJsonPath('data.activity.orders_count', 1);
        $response->assertJsonPath('data.activity.invoices_count', 2);
        $response->assertJsonPath('data.activity.total_invoiced_amount', '7000.00');
    }

    public function test_it_reports_review_and_dispute_aggregates(): void
    {
        $garage = Garage::factory()->create();
        Review::factory()->forGarage($garage)->create(['rating' => 5]);
        Review::factory()->forGarage($garage)->create(['rating' => 3]);
        Review::factory()->forGarage($garage)->hidden()->create(['rating' => 1]);

        Dispute::factory()->forGarage($garage)->create(['status' => DisputeStatus::Submitted]);
        Dispute::factory()->forGarage($garage)->resolvedFounded()->create();

        Sanctum::actingAs($this->admin());

        $response = $this->getJson('/api/admin/statistics');

        $response->assertOk();
        $response->assertJsonPath('data.reviews.count', 2);
        $response->assertJsonPath('data.reviews.average_rating', 4);
        $response->assertJsonPath('data.disputes.total', 2);
        $response->assertJsonPath('data.disputes.by_status.'.DisputeStatus::Submitted->value, 1);
        $response->assertJsonPath('data.disputes.by_status.'.DisputeStatus::ResolvedFounded->value, 1);
    }

    public function test_end_date_must_not_precede_start_date(): void
    {
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/admin/statistics?start_date=2026-02-01&end_date=2026-01-01')
            ->assertUnprocessable();
    }
}
