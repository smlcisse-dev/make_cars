<?php

namespace Tests\Feature\Auth;

use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Mot de passe oublié par code email (CLAUDE.md §5, ajout v0.29).
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC_MESSAGE = 'Si un compte existe avec cette adresse, un code vient d\'être envoyé.';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function forgot(string $email = 'awa@garage.test'): TestResponse
    {
        return $this->postJson('/api/auth/password/forgot', ['email' => $email]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function reset(string $code, array $overrides = []): TestResponse
    {
        return $this->postJson('/api/auth/password/reset', [
            'email' => 'awa@garage.test',
            'code' => $code,
            'password' => 'NouveauMotDePasse1',
            'password_confirmation' => 'NouveauMotDePasse1',
            ...$overrides,
        ]);
    }

    private function lastSentCode(): string
    {
        $code = null;

        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    private function wrongCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }

    private function createUser(array $attributes = []): User
    {
        return User::factory()->garagiste()->create(['email' => 'awa@garage.test', 'password' => 'AncienMotDePasse1', ...$attributes]);
    }

    public function test_a_code_is_sent_to_an_existing_account(): void
    {
        $this->createUser();

        $this->forgot()->assertOk()->assertJsonPath('message', self::GENERIC_MESSAGE);

        Mail::assertSent(PasswordResetCodeMail::class, fn (PasswordResetCodeMail $mail) => $mail->hasTo('awa@garage.test'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->lastSentCode());
        $this->assertArrayNotHasKey('code_hash', PasswordResetCode::sole()->toArray());
    }

    public function test_an_unknown_email_gets_the_same_response_and_no_email(): void
    {
        $this->forgot('personne@nulle.part')->assertOk()->assertJsonPath('message', self::GENERIC_MESSAGE);

        Mail::assertNothingSent();
    }

    public function test_a_code_for_an_unknown_email_behaves_like_a_wrong_code(): void
    {
        $this->forgot('personne@nulle.part');

        $this->reset('123456', ['email' => 'personne@nulle.part'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'reset_code_invalid')
            ->assertJsonPath('remaining_attempts', 4);
    }

    public function test_an_email_without_pending_request_gets_the_wrong_code_response(): void
    {
        $this->createUser();

        $this->reset('123456')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'reset_code_invalid')
            ->assertJsonStructure(['remaining_attempts']);
    }

    public function test_the_right_code_changes_the_password(): void
    {
        $this->createUser();
        $this->forgot();

        $this->reset($this->lastSentCode())
            ->assertOk()
            ->assertJsonPath('message', 'Votre mot de passe a été modifié. Connectez-vous avec votre nouveau mot de passe.')
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseCount('password_reset_codes', 0);

        $this->postJson('/api/auth/login', ['email' => 'awa@garage.test', 'password' => 'AncienMotDePasse1'])
            ->assertUnprocessable();
        $this->postJson('/api/auth/login', ['email' => 'awa@garage.test', 'password' => 'NouveauMotDePasse1'])
            ->assertOk();
    }

    public function test_changing_the_password_revokes_every_session(): void
    {
        $user = $this->createUser();
        $user->createToken('ordinateur');
        $user->createToken('telephone');
        $this->forgot();

        $this->reset($this->lastSentCode())->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_an_express_account_leaves_the_express_status(): void
    {
        $this->createUser(['role' => 'automobiliste', 'is_express' => true, 'password' => null]);
        $this->forgot();

        $this->reset($this->lastSentCode())->assertOk();

        $this->assertFalse(User::sole()->is_express);
    }

    public function test_a_wrong_code_decrements_the_remaining_attempts(): void
    {
        $this->createUser();
        $this->forgot();
        $wrong = $this->wrongCode($this->lastSentCode());

        $this->reset($wrong)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'reset_code_invalid')
            ->assertJsonPath('remaining_attempts', 4);

        $this->reset($wrong)->assertJsonPath('remaining_attempts', 3);
    }

    public function test_the_request_is_locked_at_the_fifth_failure_even_with_the_right_code(): void
    {
        $this->createUser();
        $this->forgot();
        $code = $this->lastSentCode();

        foreach (range(1, 4) as $attempt) {
            $this->reset($this->wrongCode($code))->assertJsonPath('code', 'reset_code_invalid');
        }
        $this->reset($this->wrongCode($code))->assertUnprocessable()->assertJsonPath('code', 'reset_code_locked');

        $this->reset($code)->assertUnprocessable()->assertJsonPath('code', 'reset_code_locked');
        $this->postJson('/api/auth/login', ['email' => 'awa@garage.test', 'password' => 'AncienMotDePasse1'])->assertOk();
    }

    public function test_an_expired_code_is_refused(): void
    {
        $this->createUser();
        $this->forgot();
        $code = $this->lastSentCode();

        $this->travel(16)->minutes();

        $this->reset($code)->assertUnprocessable()->assertJsonPath('code', 'reset_code_expired');
    }

    public function test_a_new_request_within_60_seconds_sends_nothing_and_answers_the_same(): void
    {
        $this->createUser();
        $this->forgot();
        $code = $this->lastSentCode();

        $this->travel(30)->seconds();
        $this->forgot()->assertOk()->assertJsonPath('message', self::GENERIC_MESSAGE);

        Mail::assertSentCount(1);
        $this->reset($code)->assertOk();
    }

    public function test_a_new_request_after_60_seconds_replaces_the_code_and_resets_the_attempts(): void
    {
        $this->createUser();
        $this->forgot();
        $oldCode = $this->lastSentCode();
        $this->reset($this->wrongCode($oldCode))->assertJsonPath('remaining_attempts', 4);

        $this->travel(61)->seconds();
        Mail::fake();
        $this->forgot();
        $newCode = $this->lastSentCode();

        $this->assertSame(5, PasswordResetCode::sole()->attempts_left);
        if ($oldCode !== $newCode) {
            $this->reset($oldCode)->assertJsonPath('code', 'reset_code_invalid');
        }
        $this->reset($newCode)->assertOk();
    }

    public function test_the_new_password_must_follow_the_password_rules(): void
    {
        $this->createUser();
        $this->forgot();

        $this->reset($this->lastSentCode(), ['password' => 'court', 'password_confirmation' => 'court'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertSame(5, PasswordResetCode::sole()->attempts_left);
    }

    public function test_a_malformed_code_is_a_validation_error_that_does_not_consume_an_attempt(): void
    {
        $this->createUser();
        $this->forgot();

        $this->reset('12ab')->assertUnprocessable()->assertJsonValidationErrors('code');

        $this->assertSame(5, PasswordResetCode::sole()->attempts_left);
    }
}
