<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Limites de débit de la connexion (CLAUDE.md §4) : 5 échecs par minute
 * pour un même email depuis une même IP, 20 tentatives par minute par IP ;
 * une connexion réussie remet le compteur email + IP à zéro.
 */
class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $email, string $password = 'mauvais-mot-de-passe', string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/login', ['email' => $email, 'password' => $password]);
    }

    public function test_the_sixth_failed_attempt_on_the_same_email_is_throttled_with_a_french_message(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        foreach (range(1, 5) as $i) {
            $this->attempt($user->email)->assertUnprocessable();
        }

        $response = $this->attempt($user->email)->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');
        $retryAfter = $response->json('retry_after');
        $this->assertIsInt($retryAfter);
        $this->assertGreaterThan(0, $retryAfter);
        $response->assertJsonPath('message', "Trop de tentatives. Réessayez dans {$retryAfter} secondes.");

        // Même le bon mot de passe est refusé pendant l'attente.
        $this->attempt($user->email, 'password')->assertStatus(429);
    }

    public function test_the_limit_is_lifted_after_the_waiting_time(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        foreach (range(1, 6) as $i) {
            $this->attempt($user->email);
        }

        $this->travel(61)->seconds();

        $this->attempt($user->email, 'password')->assertOk();
    }

    public function test_a_successful_login_resets_the_failed_attempts_counter(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        foreach (range(1, 4) as $i) {
            $this->attempt($user->email)->assertUnprocessable();
        }
        $this->attempt($user->email, 'password')->assertOk();

        foreach (range(1, 5) as $i) {
            $this->attempt($user->email)->assertUnprocessable();
        }
        $this->attempt($user->email)->assertStatus(429);
    }

    public function test_the_email_limit_does_not_block_another_ip(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);
        foreach (range(1, 6) as $i) {
            $this->attempt($user->email);
        }

        $this->attempt($user->email, 'password', '10.0.0.2')->assertOk();
    }

    public function test_an_ip_is_throttled_after_twenty_attempts_across_emails(): void
    {
        foreach (range(1, 20) as $i) {
            $this->attempt("inconnu{$i}@example.com")->assertUnprocessable();
        }

        $this->attempt('encore-un@example.com')->assertStatus(429);
    }
}
