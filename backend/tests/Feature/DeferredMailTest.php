<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\User;
use App\Support\DeferredMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Emails envoyés après la réponse HTTP (DeferredMail) : la personne n'attend
 * pas le fournisseur d'emails pour voir la page suivante.
 */
class DeferredMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_is_sent_only_once_the_response_is_ready(): void
    {
        Mail::fake();
        $sentBeforeResponse = null;

        Route::post('/_test/deferred-mail', function () use (&$sentBeforeResponse) {
            DeferredMail::send('client@example.com', new PasswordResetCodeMail('123456', 15));
            $sentBeforeResponse = Mail::sent(PasswordResetCodeMail::class)->count();

            return response()->json(['ok' => true]);
        });

        $this->postJson('/_test/deferred-mail')->assertOk();

        $this->assertSame(0, $sentBeforeResponse);
        Mail::assertSent(PasswordResetCodeMail::class, fn ($mail) => $mail->hasTo('client@example.com'));
    }

    public function test_no_email_is_sent_when_the_request_ends_in_error(): void
    {
        Mail::fake();

        Route::post('/_test/deferred-mail', function () {
            DeferredMail::send('client@example.com', new PasswordResetCodeMail('123456', 15));

            return response()->json(['message' => 'Refusé'], 422);
        });

        $this->postJson('/_test/deferred-mail')->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_a_sending_failure_is_logged_without_breaking_the_response(): void
    {
        Exceptions::fake();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Fournisseur injoignable'));

        Route::post('/_test/deferred-mail', function () {
            DeferredMail::send('client@example.com', new PasswordResetCodeMail('123456', 15));

            return response()->json(['ok' => true]);
        });

        $this->postJson('/_test/deferred-mail')->assertOk();

        Exceptions::assertReported(fn (RuntimeException $exception) => $exception->getMessage() === 'Fournisseur injoignable');
    }

    public function test_the_forgotten_password_code_is_still_emailed(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'pro@example.com']);

        $this->postJson('/api/auth/password/forgot', ['email' => 'pro@example.com'])->assertOk();

        Mail::assertSent(PasswordResetCodeMail::class, fn ($mail) => $mail->hasTo('pro@example.com'));
    }
}
