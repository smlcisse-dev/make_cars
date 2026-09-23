<?php

namespace Tests\Feature;

use App\Enums\RegistrationStatus;
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
}
