<?php

namespace Tests\Feature\Auth;

use App\Mail\ExpressClientClaimMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Réclamation d'un compte "express" par son propriétaire réel (CLAUDE.md §5,
 * ajout v0.17) : même mécanisme de lien signé que la validation de devis par
 * email (ajout v0.9).
 */
class ExpressClaimTest extends TestCase
{
    use RefreshDatabase;

    private function expressClient(): User
    {
        return User::factory()->create(['is_express' => true, 'email' => 'express@example.com']);
    }

    public function test_requesting_a_claim_link_dispatches_an_email(): void
    {
        Mail::fake();
        $this->expressClient();

        $this->postJson('/api/auth/express-claim', ['email' => 'express@example.com'])->assertOk();

        Mail::assertSent(ExpressClientClaimMail::class, fn ($mail) => $mail->hasTo('express@example.com'));
    }

    public function test_requesting_a_claim_link_fails_for_an_unknown_email(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/express-claim', ['email' => 'nobody@example.com'])
            ->assertUnprocessable();

        Mail::assertNothingSent();
    }

    public function test_requesting_a_claim_link_fails_for_a_non_express_account(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'regular@example.com']);

        $this->postJson('/api/auth/express-claim', ['email' => 'regular@example.com'])
            ->assertUnprocessable();

        Mail::assertNothingSent();
    }

    private function signedPath(User $client, int $days = 1): string
    {
        return URL::temporarySignedRoute('express-clients.claim.show', now()->addDays($days), ['user' => $client->id], absolute: false);
    }

    public function test_the_email_link_points_to_the_frontend_page_with_a_relative_signed_link(): void
    {
        Mail::fake();
        config(['app.frontend_url' => 'https://app.makecars.test']);
        $client = $this->expressClient();

        $this->postJson('/api/auth/express-claim', ['email' => 'express@example.com'])->assertOk();

        Mail::assertSent(ExpressClientClaimMail::class, function (ExpressClientClaimMail $mail) use ($client) {
            parse_str((string) parse_url($mail->claimUrl, PHP_URL_QUERY), $query);

            return str_starts_with($mail->claimUrl, 'https://app.makecars.test/compte/activer?')
                && str_starts_with($query['link'], "/api/express-clients/{$client->id}/claim?");
        });
    }

    public function test_opening_the_link_shows_the_first_name_and_masked_email_without_any_effect(): void
    {
        $client = User::factory()->create([
            'is_express' => true, 'email' => 'jean@gmail.com', 'first_name' => 'Jean', 'last_name' => 'Dossou',
        ]);
        $passwordBefore = $client->password;

        $this->getJson($this->signedPath($client))
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Jean')
            ->assertJsonPath('data.masked_email', 'j***@gmail.com')
            ->assertJsonMissingPath('data.email');

        $client->refresh();
        $this->assertTrue($client->is_express);
        $this->assertSame($passwordBefore, $client->password);
    }

    public function test_the_first_name_falls_back_to_the_first_word_of_the_name(): void
    {
        $client = User::factory()->create(['is_express' => true, 'email' => 'express@example.com', 'name' => 'Awa Koffi', 'first_name' => null]);

        $this->getJson($this->signedPath($client))->assertOk()->assertJsonPath('data.first_name', 'Awa');
    }

    public function test_the_signed_link_sets_a_new_password_and_unmarks_the_account_as_express(): void
    {
        $client = $this->expressClient();

        $response = $this->postJson($this->signedPath($client), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertOk()->assertJsonPath('data.is_express', false);
        $client->refresh();
        $this->assertFalse($client->is_express);
        $this->assertTrue(Hash::check('new-secure-password', $client->password));
    }

    public function test_the_link_stays_valid_through_another_host(): void
    {
        config(['app.url' => 'http://localhost']);
        $client = $this->expressClient();

        $this->getJson('http://127.0.0.1:8000'.$this->signedPath($client))->assertOk();
    }

    public function test_an_unsigned_or_tampered_link_is_rejected(): void
    {
        $client = $this->expressClient();
        $other = User::factory()->create(['is_express' => true, 'email' => 'other@example.com']);
        $path = $this->signedPath($client);

        $this->getJson("/api/express-clients/{$client->id}/claim?signature=invalid")->assertForbidden();
        $this->postJson(str_replace("/express-clients/{$client->id}/", "/express-clients/{$other->id}/", $path), [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertForbidden();
        $this->assertTrue($other->fresh()->is_express);
    }

    public function test_an_expired_link_is_rejected(): void
    {
        $client = $this->expressClient();
        $path = $this->signedPath($client, days: 7);

        $this->travel(8)->days();

        $this->getJson($path)->assertForbidden();
        $this->postJson($path, [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertForbidden();
        $this->assertTrue($client->fresh()->is_express);
    }

    public function test_an_already_claimed_account_cannot_be_claimed_again(): void
    {
        $client = $this->expressClient();
        $client->update(['password' => 'already-set', 'is_express' => false]);

        $this->getJson($this->signedPath($client))->assertStatus(409);
    }

    /**
     * Un lien signé expiré (ou perdu) n'empêche pas d'en redemander un
     * nouveau tant que le compte reste "express" (non encore réclamé).
     */
    public function test_a_new_link_can_be_requested_as_long_as_the_account_is_still_unclaimed(): void
    {
        Mail::fake();
        $this->expressClient();

        $this->postJson('/api/auth/express-claim', ['email' => 'express@example.com'])->assertOk();
        $this->postJson('/api/auth/express-claim', ['email' => 'express@example.com'])->assertOk();

        Mail::assertSent(ExpressClientClaimMail::class, 2);
    }
}
