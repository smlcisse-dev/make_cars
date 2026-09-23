<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_display_name_is_recomposed_from_first_and_last_name(): void
    {
        $user = User::factory()->create(['name' => 'Ancien nom', 'first_name' => 'Moussa', 'last_name' => 'Adéchi']);

        $this->assertSame('Moussa Adéchi', $user->fresh()->name);

        $user->update(['first_name' => 'Aïcha']);

        $this->assertSame('Aïcha Adéchi', $user->fresh()->name);
    }

    public function test_a_user_without_first_and_last_name_keeps_its_name(): void
    {
        $user = User::factory()->create(['name' => 'Koffi Mensah']);

        $user->update(['phone' => '+2290199887766']);

        $fresh = $user->fresh();
        $this->assertSame('Koffi Mensah', $fresh->name);
        $this->assertNull($fresh->first_name);
        $this->assertNull($fresh->last_name);
    }

    public function test_a_single_filled_part_does_not_override_the_name(): void
    {
        $user = User::factory()->create(['name' => 'Koffi Mensah', 'first_name' => 'Koffi']);

        $this->assertSame('Koffi Mensah', $user->fresh()->name);
    }

    public function test_the_user_resource_exposes_first_and_last_name(): void
    {
        $user = User::factory()->create(['first_name' => 'Moussa', 'last_name' => 'Adéchi']);

        $this->actingAs($user)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Moussa')
            ->assertJsonPath('data.last_name', 'Adéchi')
            ->assertJsonPath('data.name', 'Moussa Adéchi');
    }
}
