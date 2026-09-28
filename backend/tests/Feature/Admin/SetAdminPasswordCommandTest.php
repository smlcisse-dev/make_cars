<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetAdminPasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG_PASSWORD = 'Nouveau-Mot2Passe!';

    public function test_an_unknown_email_is_refused(): void
    {
        $this->artisan('admin:set-password', ['email' => 'inconnu@makecars.test'])
            ->expectsOutputToContain("Aucun compte n'existe")
            ->assertFailed();
    }

    public function test_a_non_admin_account_is_refused_without_asking_for_a_password(): void
    {
        $accounts = [
            User::factory()->create(['password' => 'ancien-mot-de-passe']), // automobiliste
            User::factory()->garagiste()->create(['password' => 'ancien-mot-de-passe']),
            User::factory()->marketSpace()->create(['password' => 'ancien-mot-de-passe']),
        ];

        foreach ($accounts as $user) {
            $this->artisan('admin:set-password', ['email' => $user->email])
                ->expectsOutputToContain("n'est pas un compte administrateur")
                ->assertFailed();

            $this->assertTrue(Hash::check('ancien-mot-de-passe', $user->fresh()->password));
        }
    }

    public function test_a_weak_password_is_refused(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'ancien-mot-de-passe']);
        $admin->createToken('web');

        $this->artisan('admin:set-password', ['email' => $admin->email])
            ->expectsQuestion('Nouveau mot de passe', 'motdepasse12')
            ->expectsQuestion('Confirmez le nouveau mot de passe', 'motdepasse12')
            ->expectsOutputToContain('Mot de passe refusé')
            ->assertFailed();

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $admin->fresh()->password));
        $this->assertSame(1, $admin->tokens()->count());
    }

    public function test_mismatching_entries_are_refused(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'ancien-mot-de-passe']);

        $this->artisan('admin:set-password', ['email' => $admin->email])
            ->expectsQuestion('Nouveau mot de passe', self::STRONG_PASSWORD)
            ->expectsQuestion('Confirmez le nouveau mot de passe', self::STRONG_PASSWORD.'x')
            ->expectsOutputToContain('ne correspondent pas')
            ->assertFailed();

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $admin->fresh()->password));
    }

    public function test_the_password_is_changed_and_every_token_is_revoked(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'ancien-mot-de-passe']);
        $admin->createToken('web');
        $admin->createToken('essai');
        $other = User::factory()->admin()->create();
        $other->createToken('web');

        $this->artisan('admin:set-password', ['email' => $admin->email])
            ->expectsQuestion('Nouveau mot de passe', self::STRONG_PASSWORD)
            ->expectsQuestion('Confirmez le nouveau mot de passe', self::STRONG_PASSWORD)
            ->expectsOutputToContain('Jetons révoqués : 2')
            ->doesntExpectOutputToContain(self::STRONG_PASSWORD)
            ->assertSuccessful();

        $fresh = $admin->fresh();
        $this->assertTrue(Hash::check(self::STRONG_PASSWORD, $fresh->password));
        $this->assertSame(0, $fresh->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());

        $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => self::STRONG_PASSWORD])
            ->assertOk();
    }
}
