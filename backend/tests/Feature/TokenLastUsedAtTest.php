<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `sanctum.last_used_at` (SANCTUM_TRACK_LAST_USED_AT) : désactivé en
 * production, une requête authentifiée n'écrit plus rien dans
 * personal_access_tokens.
 */
class TokenLastUsedAtTest extends TestCase
{
    use RefreshDatabase;

    private function callWithToken(User $user): void
    {
        $token = $user->createToken('test');

        $this->getJson('/api/auth/me', ['Authorization' => 'Bearer '.$token->plainTextToken])->assertOk();
    }

    public function test_the_token_last_use_is_recorded_by_default(): void
    {
        $user = User::factory()->create();

        $this->callWithToken($user);

        $this->assertNotNull($user->tokens()->first()->last_used_at);
    }

    public function test_the_token_last_use_is_not_written_when_disabled(): void
    {
        config(['sanctum.last_used_at' => false]);
        $user = User::factory()->create();

        $this->callWithToken($user);

        $this->assertNull($user->tokens()->first()->last_used_at);
    }
}
