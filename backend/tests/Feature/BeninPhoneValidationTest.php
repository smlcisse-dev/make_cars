<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\User;
use App\Rules\BeninPhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BeninPhoneValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function phoneInputs(): array
    {
        return [
            'canonical' => ['+2290123456789', '+2290123456789'],
            'spaces' => ['+229 01 23 45 67 89', '+2290123456789'],
            'dashes and dots' => ['+229-01-23-45-67-89', '+2290123456789'],
            'parentheses' => ['+229 (01) 23.45.67.89', '+2290123456789'],
            '00229 prefix' => ['00229 01 23 45 67 89', '+2290123456789'],
            'text' => ['azerty', null],
            'old 8-digit format' => ['+22997000000', null],
            'wrong leading digits' => ['+229 02 23 45 67 89', null],
            'too short' => ['+229 01 23 45 67', null],
            'too long' => ['+229 01 23 45 67 890', null],
            'no country code' => ['01 23 45 67 89', null],
            'other country' => ['+33 01 23 45 67 89', null],
        ];
    }

    #[DataProvider('phoneInputs')]
    public function test_the_normalizer_accepts_only_the_beninese_ten_digit_structure(string $input, ?string $expected): void
    {
        $this->assertSame($expected, BeninPhoneNumber::normalize($input));
    }

    public function test_the_garage_profile_rejects_an_invalid_phone_and_stores_the_canonical_form(): void
    {
        $garage = Garage::factory()->complete()->create();
        Sanctum::actingAs($garage->user);
        $payload = $this->profilePayload($garage);

        $this->putJson('/api/garage/profile', [...$payload, 'phone' => 'azerty'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->putJson('/api/garage/profile', [...$payload, 'phone' => '+229 01 23 45 67 89'])
            ->assertOk()
            ->assertJsonPath('data.phone', '+2290123456789');
    }

    public function test_the_market_space_profile_rejects_an_invalid_phone(): void
    {
        $account = MarketSpaceAccount::factory()->complete()->create();
        Sanctum::actingAs($account->user);

        $this->putJson('/api/market-space/profile', [...$this->profilePayload($account), 'phone' => '12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_the_automobiliste_registration_validates_and_normalizes_the_phone(): void
    {
        $payload = [
            'name' => 'Awa',
            'email' => 'awa@example.test',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
        ];

        $this->postJson('/api/auth/register/automobiliste', [...$payload, 'phone' => 'azerty'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->postJson('/api/auth/register/automobiliste', [...$payload, 'phone' => '+229 01 23 45 67 89'])->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'awa@example.test', 'phone' => '+2290123456789']);
    }

    public function test_the_same_number_typed_differently_is_still_a_duplicate(): void
    {
        User::factory()->create(['phone' => '+2290123456789']);

        $this->postJson('/api/auth/register/automobiliste', [
            'name' => 'Doublon',
            'email' => 'doublon@example.test',
            'password' => 'password-123',
            'password_confirmation' => 'password-123',
            'phone' => '+229 01 23 45 67 89',
        ])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_the_professional_registration_rejects_an_invalid_phone(): void
    {
        $this->postJson('/api/auth/register/professionnel', ['phone' => 'azerty'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_the_express_client_creation_rejects_an_invalid_phone(): void
    {
        $garage = Garage::factory()->complete()->create();
        Sanctum::actingAs($garage->user);

        $this->postJson('/api/garage/clients/express', ['name' => 'Client', 'email' => 'c@example.test', 'phone' => 'azerty'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(Garage|MarketSpaceAccount $profile): array
    {
        return [
            'name' => $profile->name,
            'address' => $profile->address,
            'latitude' => 6.37,
            'longitude' => 2.39,
            'department_id' => $profile->department_id,
            'commune_id' => $profile->commune_id,
            'arrondissement_id' => $profile->arrondissement_id,
            'neighborhood' => $profile->neighborhood,
        ];
    }
}
