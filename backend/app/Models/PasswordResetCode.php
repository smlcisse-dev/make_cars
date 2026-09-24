<?php

namespace App\Models;

use App\Support\EmailCode;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Demande de réinitialisation du mot de passe en cours pour un email
 * (CLAUDE.md §5, ajout v0.29). `code_hash` est le code haché. Une demande
 * existe aussi pour un email sans compte (voir PasswordResetService) : sa
 * présence ne dit donc rien de l'existence d'un compte.
 */
#[Fillable(['email', 'code_hash', 'code_expires_at', 'attempts_left', 'last_code_sent_at'])]
#[Hidden(['code_hash'])]
class PasswordResetCode extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code_expires_at' => 'datetime',
            'last_code_sent_at' => 'datetime',
            'attempts_left' => 'integer',
        ];
    }

    public function resendAvailableAt(): CarbonInterface
    {
        return EmailCode::resendAvailableAt($this->last_code_sent_at);
    }
}
