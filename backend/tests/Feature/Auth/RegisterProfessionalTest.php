<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Exceptions\ApiException;
use App\Mail\AccountCreatedMail;
use App\Mail\VerificationCodeMail;
use App\Models\PendingProfessionalRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Inscription professionnelle courte avec vérification de l'email par code
 * (CLAUDE.md §5, ajout v0.26).
 */
class RegisterProfessionalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'first_name' => 'Moussa',
            'last_name' => 'Adéchi',
            'email' => 'moussa@garage.test',
            'phone' => '+229 01 23 45 67 89',
            'password' => 'password',
            'password_confirmation' => 'password',
            'account_type' => 'garagiste',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function register(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/auth/register/professionnel', $this->payload($overrides));
    }

    private function lastSentCode(): string
    {
        $code = null;

        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    private function wrongCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }

    private function verify(string $uuid, string $code): TestResponse
    {
        return $this->postJson("/api/auth/register/professionnel/{$uuid}/verify", ['code' => $code]);
    }

    public function test_a_valid_registration_creates_a_pending_request_and_sends_a_code_without_creating_an_account(): void
    {
        $response = $this->register();

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['verification_id', 'email', 'code_expires_at', 'resend_available_at'], 'message'])
            ->assertJsonPath('data.email', 'moussa@garage.test')
            ->assertJsonMissingPath('data.token')
            ->assertJsonMissingPath('data.user');

        $this->assertDatabaseCount('users', 0);
        $pending = PendingProfessionalRegistration::sole();
        $this->assertSame($response->json('data.verification_id'), $pending->uuid);
        $this->assertSame('+2290123456789', $pending->phone);
        $this->assertNotSame('password', $pending->password);
        $this->assertArrayNotHasKey('password', $pending->toArray());
        $this->assertArrayNotHasKey('code_hash', $pending->toArray());

        $code = $this->lastSentCode();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        Mail::assertSent(VerificationCodeMail::class, fn (VerificationCodeMail $mail) => $mail->hasTo('moussa@garage.test'));
    }

    public function test_the_email_must_not_already_belong_to_an_account(): void
    {
        User::factory()->create(['email' => 'moussa@garage.test']);

        $this->register()->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_validation_messages_are_in_french(): void
    {
        User::factory()->create(['email' => 'moussa@garage.test']);

        $this->register(['first_name' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Cet email est déjà utilisé par un autre compte.')
            ->assertJsonPath('errors.first_name.0', 'Le champ prénom est obligatoire.');
    }

    public function test_the_phone_must_not_already_belong_to_an_account(): void
    {
        User::factory()->create(['phone' => '+2290123456789']);

        $this->register()->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_the_phone_is_required(): void
    {
        $this->register(['phone' => null])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_the_phone_must_be_a_valid_benin_number(): void
    {
        $this->register(['phone' => '97 00 00 00'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_first_and_last_name_are_required_and_capped_at_100_characters(): void
    {
        $this->register(['first_name' => '', 'last_name' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    public function test_account_type_must_be_garagiste_or_market_space(): void
    {
        $this->register(['account_type' => 'admin'])->assertUnprocessable()->assertJsonValidationErrors('account_type');
    }

    public function test_registering_again_replaces_the_previous_request_for_the_same_email_and_purges_stale_requests(): void
    {
        $this->register()->assertCreated();
        $first = PendingProfessionalRegistration::sole();

        $this->travel(25)->hours();
        $this->register(['email' => 'autre@garage.test', 'phone' => '+2290198765432'])->assertCreated();
        $this->assertDatabaseMissing('pending_professional_registrations', ['id' => $first->id]);

        $this->travelBack();
        $this->register(['email' => 'autre@garage.test', 'phone' => '+2290198765432'])->assertCreated();
        $this->assertSame(1, PendingProfessionalRegistration::where('email', 'autre@garage.test')->count());
    }

    public function test_the_correct_code_creates_the_account_the_empty_profile_and_the_registration(): void
    {
        $uuid = $this->register()->json('data.verification_id');

        $response = $this->verify($uuid, $this->lastSentCode());

        $response->assertCreated()
            ->assertJsonPath('data.email', 'moussa@garage.test')
            ->assertJsonMissingPath('data.token');

        $user = User::sole();
        $this->assertSame(AccountType::Garagiste, $user->role);
        $this->assertSame('Moussa', $user->first_name);
        $this->assertSame('Adéchi', $user->last_name);
        $this->assertSame('Moussa Adéchi', $user->name);
        $this->assertSame('+2290123456789', $user->phone);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(RegistrationStatus::ProfileIncomplete, $user->professionalRegistration->status);
        $this->assertNotNull($user->garage);
        $this->assertNull($user->garage->name);
        $this->assertSame('+2290123456789', $user->garage->phone);
        $this->assertDatabaseCount('pending_professional_registrations', 0);

        Mail::assertSent(AccountCreatedMail::class, fn (AccountCreatedMail $mail) => $mail->hasTo('moussa@garage.test')
            && $mail->profileUrl === 'http://localhost:5173/garage/profile');
    }

    public function test_a_wrong_code_decrements_the_remaining_attempts(): void
    {
        $uuid = $this->register()->json('data.verification_id');
        $wrong = $this->wrongCode($this->lastSentCode());

        $this->verify($uuid, $wrong)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'verification_code_invalid')
            ->assertJsonPath('remaining_attempts', 4);

        $this->verify($uuid, $wrong)->assertJsonPath('remaining_attempts', 3);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_business_refusal_is_not_reported_to_the_error_log(): void
    {
        Exceptions::fake();

        $uuid = $this->register()->json('data.verification_id');

        $this->verify($uuid, $this->wrongCode($this->lastSentCode()))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'verification_code_invalid');

        Exceptions::assertNotReported(ApiException::class);
    }

    public function test_the_request_is_locked_at_the_fifth_failure(): void
    {
        $uuid = $this->register()->json('data.verification_id');
        $code = $this->lastSentCode();
        $wrong = $this->wrongCode($code);

        for ($i = 0; $i < 4; $i++) {
            $this->verify($uuid, $wrong)->assertJsonPath('code', 'verification_code_invalid');
        }

        $this->verify($uuid, $wrong)->assertUnprocessable()->assertJsonPath('code', 'verification_locked');

        // Même le bon code est refusé une fois verrouillé.
        $this->verify($uuid, $code)->assertUnprocessable()->assertJsonPath('code', 'verification_locked');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $uuid = $this->register()->json('data.verification_id');
        $code = $this->lastSentCode();

        $this->travel(16)->minutes();

        $this->verify($uuid, $code)->assertUnprocessable()->assertJsonPath('code', 'verification_expired');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_malformed_code_is_a_validation_error_that_does_not_consume_an_attempt(): void
    {
        $uuid = $this->register()->json('data.verification_id');

        $this->verify($uuid, '12ab')->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertSame(5, PendingProfessionalRegistration::sole()->attempts_left);
    }

    public function test_resending_too_early_is_refused_with_429(): void
    {
        $uuid = $this->register()->json('data.verification_id');

        $this->travel(30)->seconds();

        $this->postJson("/api/auth/register/professionnel/{$uuid}/resend")
            ->assertStatus(429)
            ->assertJsonPath('code', 'resend_too_soon')
            ->assertJsonPath('retry_after_seconds', 30);
    }

    public function test_after_a_resend_the_old_code_is_refused_and_attempts_are_reset(): void
    {
        $uuid = $this->register()->json('data.verification_id');
        $oldCode = $this->lastSentCode();
        $this->verify($uuid, $this->wrongCode($oldCode))->assertJsonPath('remaining_attempts', 4);

        $this->travel(61)->seconds();
        $this->postJson("/api/auth/register/professionnel/{$uuid}/resend")
            ->assertOk()
            ->assertJsonStructure(['data' => ['code_expires_at', 'resend_available_at']]);
        Mail::assertSent(VerificationCodeMail::class, 2);
        $this->assertSame(5, PendingProfessionalRegistration::sole()->attempts_left);

        $codes = [];
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$codes) {
            $codes[] = $mail->code;

            return true;
        });
        [$first, $newCode] = $codes;

        if ($first !== $newCode) {
            $this->verify($uuid, $first)->assertJsonPath('code', 'verification_code_invalid');
        }
        $this->verify($uuid, $newCode)->assertCreated();
    }

    public function test_an_unknown_uuid_returns_404(): void
    {
        $uuid = '9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d';

        $this->verify($uuid, '123456')->assertNotFound()->assertJsonPath('code', 'verification_not_found');
        $this->postJson("/api/auth/register/professionnel/{$uuid}/resend")->assertNotFound()->assertJsonPath('code', 'verification_not_found');
    }

    public function test_an_already_used_request_returns_404(): void
    {
        $uuid = $this->register()->json('data.verification_id');
        $code = $this->lastSentCode();
        $this->verify($uuid, $code)->assertCreated();

        $this->verify($uuid, $code)->assertNotFound()->assertJsonPath('code', 'verification_not_found');
    }

    public function test_a_request_older_than_24_hours_is_treated_as_not_found(): void
    {
        $uuid = $this->register()->json('data.verification_id');

        $this->travel(25)->hours();

        $this->postJson("/api/auth/register/professionnel/{$uuid}/resend")->assertNotFound();
        $this->assertDatabaseCount('pending_professional_registrations', 0);
    }

    public function test_an_email_taken_in_the_meantime_is_refused_at_verification(): void
    {
        $uuid = $this->register()->json('data.verification_id');
        $code = $this->lastSentCode();
        User::factory()->create(['email' => 'moussa@garage.test']);

        $this->verify($uuid, $code)->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('professional_registrations', 0);
        $this->assertDatabaseCount('garages', 0);
        Mail::assertNotSent(AccountCreatedMail::class);
    }

    public function test_the_chosen_password_can_then_be_used_to_log_in(): void
    {
        $uuid = $this->register(['password' => 'MotDePasse123', 'password_confirmation' => 'MotDePasse123'])->json('data.verification_id');
        $this->verify($uuid, $this->lastSentCode())->assertCreated();

        $this->postJson('/api/auth/login', ['email' => 'moussa@garage.test', 'password' => 'MotDePasse123'])
            ->assertOk()
            ->assertJsonPath('data.user.professional_registration.status', RegistrationStatus::ProfileIncomplete->value)
            ->assertJsonStructure(['data' => ['token']]);
    }
}
