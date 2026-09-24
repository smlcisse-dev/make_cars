<?php

namespace Tests\Feature\MarketSpace;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Mail\AccountCreatedMail;
use App\Mail\VerificationCodeMail;
use App\Models\Commune;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Parcours d'inscription d'une boutique Market Space de bout en bout, par les
 * seuls appels HTTP que fait le frontend (CLAUDE.md §5, ajouts v0.26 et
 * v0.27) : inscription, code, compte vide, connexion, profil, informations
 * légales, soumission, approbation admin, accès aux routes métier.
 *
 * Les cas limites de chaque étape (codes faux, verrous, formats…) sont
 * couverts par RegisterProfessionalTest, ProfessionalDossierTest et
 * IdentityCertificateTest : ce test vérifie seulement que les étapes
 * s'enchaînent pour une boutique.
 */
class MarketSpaceSignupJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_shop_goes_from_signup_to_approved_business_access(): void
    {
        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');

        // 1. Inscription courte, type de compte « market_space ».
        $uuid = $this->postJson('/api/auth/register/professionnel', [
            'first_name' => 'Awa',
            'last_name' => 'Houngbédji',
            'email' => 'awa@boutique.test',
            'phone' => '+229 01 97 00 00 01',
            'password' => 'MotDePasse123',
            'password_confirmation' => 'MotDePasse123',
            'account_type' => 'market_space',
        ])->assertCreated()->json('data.verification_id');

        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('awa@boutique.test');
        });

        // 2. Code correct : compte Market Space, profil vide, dossier incomplet.
        $this->postJson("/api/auth/register/professionnel/{$uuid}/verify", ['code' => $code])
            ->assertCreated()
            ->assertJsonPath('data.account_type', 'market_space');

        $user = User::sole();
        $this->assertSame(AccountType::MarketSpace, $user->role);
        $this->assertNull($user->garage);
        $this->assertNotNull($user->marketSpaceAccount);
        $this->assertNull($user->marketSpaceAccount->name);
        $this->assertSame(RegistrationStatus::ProfileIncomplete, $user->professionalRegistration->status);

        Mail::assertSent(AccountCreatedMail::class, fn (AccountCreatedMail $mail) => $mail->hasTo('awa@boutique.test')
            && str_ends_with($mail->profileUrl, '/market-space/profile'));

        // 3. Connexion : le profil est incomplet, les routes métier fermées.
        $token = $this->postJson('/api/auth/login', ['email' => 'awa@boutique.test', 'password' => 'MotDePasse123'])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'market_space')
            ->assertJsonPath('data.user.profile_status.is_complete', false)
            ->json('data.token');

        $this->as($token)->getJson('/api/market-space/products')
            ->assertForbidden()
            ->assertJsonPath('code', 'registration_not_approved');

        // 4. Profil public : informations, horaires, photo.
        $commune = Commune::where('slug', 'cotonou')->firstOrFail();

        $this->as($token)->putJson('/api/market-space/profile', [
            'name' => 'Pièces Auto Vedoko',
            'address' => 'Carrefour Vedoko, Cotonou',
            'phone' => '+229 01 97 00 00 01',
            'department_id' => Department::where('slug', 'littoral')->firstOrFail()->id,
            'commune_id' => $commune->id,
            'arrondissement_id' => $commune->arrondissements()->firstOrFail()->id,
            'neighborhood' => 'Vedoko',
            'latitude' => 6.3776,
            'longitude' => 2.3947,
        ])->assertOk();

        $hours = [];
        foreach (range(1, 7) as $day) {
            $hours[] = [
                'day_of_week' => $day,
                'is_closed' => $day === 7,
                'opens_at' => $day === 7 ? null : '08:00',
                'closes_at' => $day === 7 ? null : '18:00',
            ];
        }
        $this->as($token)->putJson('/api/market-space/profile/opening-hours', ['hours' => $hours])->assertOk();

        $this->as($token)->postJson('/api/market-space/profile/images', [
            'images' => [UploadedFile::fake()->image('devanture.jpg')],
        ])->assertCreated();

        $this->as($token)->getJson('/api/market-space/profile')
            ->assertOk()
            ->assertJsonPath('meta.profile_status.is_complete', true);

        // 5. Informations légales, registre de commerce et CIP, soumission.
        $this->as($token)->putJson('/api/market-space/profile/legal', [
            'business_registration_number' => 'RB/COT/24 B 54321',
            'ifu' => '3201234567890',
            'npi' => '1234567890',
        ])->assertOk();

        $this->as($token)->post('/api/market-space/profile/legal/document', [
            'document' => UploadedFile::fake()->create('rccm.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->as($token)->post('/api/market-space/profile/legal/identity-document', [
            'document' => UploadedFile::fake()->create('cip.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->as($token)->postJson('/api/market-space/profile/submit')->assertOk();

        $registration = $user->professionalRegistration->fresh();
        $this->assertSame(RegistrationStatus::Pending, $registration->status);

        // 6. Approbation par l'admin.
        $adminToken = User::factory()->admin()->create()->createToken('test')->plainTextToken;

        $this->as($adminToken)->postJson("/api/admin/registrations/{$registration->id}/approve")->assertOk();
        $this->assertSame(RegistrationStatus::Approved, $registration->fresh()->status);

        // 7. La boutique accède désormais à ses routes métier.
        $this->as($token)->getJson('/api/market-space/products')->assertOk();
        $this->as($token)->getJson('/api/market-space/orders')->assertOk();
        $this->as($token)->getJson('/api/market-space/conversations')->assertOk();
    }

    /**
     * Requête authentifiée par jeton, comme le frontend. Dans un test, le
     * garde d'authentification garde en mémoire l'utilisateur (et ses
     * relations déjà chargées) d'une requête à l'autre, alors qu'en réel
     * chaque requête repart de zéro : on l'oublie avant chaque appel.
     */
    private function as(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }
}
