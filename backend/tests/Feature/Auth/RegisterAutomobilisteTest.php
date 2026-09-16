<?php

namespace Tests\Feature\Auth;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterAutomobilisteTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_automobiliste_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/auth/register/automobiliste', [
            'name' => 'Awa Client',
            'email' => 'awa@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()->assertJsonPath('data.user.role', AccountType::Automobiliste->value);
        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'email' => 'awa@example.com',
            'role' => AccountType::Automobiliste->value,
        ]);
    }

    public function test_registration_fails_with_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'awa@example.com']);

        $response = $this->postJson('/api/auth/register/automobiliste', [
            'name' => 'Awa Client',
            'email' => 'awa@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_registration_fails_when_passwords_do_not_match(): void
    {
        $response = $this->postJson('/api/auth/register/automobiliste', [
            'name' => 'Awa Client',
            'email' => 'awa@example.com',
            'password' => 'password',
            'password_confirmation' => 'autre-mot-de-passe',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
