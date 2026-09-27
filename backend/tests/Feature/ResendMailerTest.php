<?php

namespace Tests\Feature;

use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Emails par API en production (Render bloque le SMTP) : MAIL_MAILER=resend
 * et RESEND_API_KEY suffisent, le paquet resend/resend-php est installé.
 */
class ResendMailerTest extends TestCase
{
    public function test_the_resend_mailer_is_available(): void
    {
        config(['services.resend.key' => 're_test_key']);

        $this->assertInstanceOf(ResendTransport::class, Mail::mailer('resend')->getSymfonyTransport());
    }
}
