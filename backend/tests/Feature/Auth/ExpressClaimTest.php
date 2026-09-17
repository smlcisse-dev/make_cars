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

    public function test_the_signed_link_shows_the_claim_is_valid(): void
    {
        $client = $this->expressClient();
        $url = URL::temporarySignedRoute('express-clients.claim.show', now()->addDay(), ['user' => $client->id]);

        $this->getJson($url)->assertOk()->assertJsonPath('data.name', $client->name);
    }

    public function test_the_signed_link_sets_a_new_password_and_unmarks_the_account_as_express(): void
    {
        $client = $this->expressClient();
        $url = URL::temporarySignedRoute('express-clients.claim.confirm', now()->addDay(), ['user' => $client->id]);

        $response = $this->postJson($url, [
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $response->assertOk()->assertJsonPath('data.is_express', false);
        $client->refresh();
        $this->assertFalse($client->is_express);
        $this->assertTrue(Hash::check('new-secure-password', $client->password));
    }

    public function test_an_unsigned_or_tampered_link_is_rejected(): void
    {
        $client = $this->expressClient();

        $this->getJson("/api/express-clients/{$client->id}/claim?signature=invalid")->assertForbidden();
    }

    public function test_an_already_claimed_account_cannot_be_claimed_again(): void
    {
        $client = $this->expressClient();
        $client->update(['password' => 'already-set', 'is_express' => false]);
        $url = URL::temporarySignedRoute('express-clients.claim.show', now()->addDay(), ['user' => $client->id]);

        $this->getJson($url)->assertStatus(409);
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
