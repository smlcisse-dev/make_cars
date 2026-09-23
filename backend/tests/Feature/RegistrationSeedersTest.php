<?php

namespace Tests\Feature;

use App\Enums\RegistrationStatus;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use Database\Seeders\CatalogTestSeeder;
use Database\Seeders\ProfessionalRegistrationTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_seeders_provide_every_status_and_are_idempotent(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        for ($run = 0; $run < 2; $run++) {
            $this->seed(ProfessionalRegistrationTestSeeder::class);
            $this->seed(CatalogTestSeeder::class);
        }

        $status = fn (string $email) => User::where('email', $email)->sole()->professionalRegistration->status;

        $this->assertSame(RegistrationStatus::ProfileIncomplete, $status('garage.nouveau.incomplete@makecars.test'));
        $this->assertSame(RegistrationStatus::Pending, $status('garage.etoile.pending@makecars.test'));
        $this->assertSame(RegistrationStatus::Pending, $status('pieces.express.pending@makecars.test'));
        $this->assertSame(RegistrationStatus::Approved, $status('garage.excellence.approved@makecars.test'));
        $this->assertSame(RegistrationStatus::Approved, $status('marche.pieces.approved@makecars.test'));
        $this->assertSame(RegistrationStatus::Rejected, $status('auto.pieces.rejected@makecars.test'));

        $pending = User::where('email', 'garage.etoile.pending@makecars.test')->sole();
        $this->assertTrue($pending->garage->isProfileComplete());
        $this->assertSame([], $pending->professionalRegistration->missingLegalFields());
        $this->assertMatchesRegularExpression('/^\d{13}$/', $pending->professionalRegistration->ifu);
        $this->assertMatchesRegularExpression('/^\d{10}$/', $pending->professionalRegistration->npi);

        $this->assertNotNull(User::where('email', 'auto.pieces.rejected@makecars.test')->sole()->professionalRegistration->rejection_reason);
        $this->assertNull(User::where('email', 'garage.nouveau.incomplete@makecars.test')->sole()->garage->name);
    }

    public function test_rerunning_the_seeders_keeps_an_approved_account_and_a_product_that_have_an_order(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(ProfessionalRegistrationTestSeeder::class);
        $this->seed(CatalogTestSeeder::class);

        $garageOwner = User::where('email', 'garage.excellence.approved@makecars.test')->sole();
        $product = $garageOwner->garage->products()->where('name', 'like', 'Huile moteur 5W30%')->sole();
        $order = Order::factory()->forGarage($garageOwner->garage)->paid()->create();
        $orderLine = OrderLine::factory()->forOrder($order)->forProduct($product, 2)->create();

        $this->seed(ProfessionalRegistrationTestSeeder::class);
        $this->seed(CatalogTestSeeder::class);

        $this->assertSame($garageOwner->id, User::where('email', 'garage.excellence.approved@makecars.test')->sole()->id);
        $this->assertModelExists($order);
        $this->assertModelExists($orderLine);
        $this->assertModelExists($product);
        // Conservé, jamais recréé en double.
        $this->assertSame(1, $garageOwner->garage->products()->where('name', 'like', 'Huile moteur 5W30%')->count());
    }

    public function test_a_pending_account_with_activity_is_not_reset(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->seed(ProfessionalRegistrationTestSeeder::class);

        $pendingOwner = User::where('email', 'garage.etoile.pending@makecars.test')->sole();
        $appointment = Appointment::factory()->forGarage($pendingOwner->garage)->create();

        $this->seed(ProfessionalRegistrationTestSeeder::class);

        $this->assertSame($pendingOwner->id, User::where('email', 'garage.etoile.pending@makecars.test')->sole()->id);
        $this->assertModelExists($pendingOwner->garage);
        $this->assertModelExists($appointment);

        // Sans historique, un compte d'état d'inscription est bien réinitialisé.
        $withoutActivityId = User::where('email', 'pieces.express.pending@makecars.test')->sole()->id;
        $this->seed(ProfessionalRegistrationTestSeeder::class);
        $this->assertNotSame($withoutActivityId, User::where('email', 'pieces.express.pending@makecars.test')->sole()->id);
    }
}
