<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Mail\PasswordResetCodeMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Support\EmailCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Mot de passe oublié par code email (CLAUDE.md §5, ajout v0.29), pour tout
 * compte qui a un email. Mêmes règles de code que l'inscription
 * professionnelle (config/email_codes.php).
 *
 * Aucune réponse ne doit révéler si un compte existe pour un email :
 * - la demande répond toujours la même chose ;
 * - une demande est enregistrée même sans compte (avec un code que personne
 *   ne reçoit) : la saisie d'un code se comporte alors exactement comme pour
 *   un vrai compte (essais décomptés, verrouillage, expiration).
 */
class PasswordResetService
{
    /**
     * Demandes dont le code a expiré depuis plus longtemps que ce délai :
     * purgées au fil de l'eau (aucune tâche planifiée ne tourne).
     */
    private const PURGE_AFTER_HOURS = 24;

    public function requestCode(string $email): void
    {
        PasswordResetCode::query()
            ->where('code_expires_at', '<', now()->subHours(self::PURGE_AFTER_HOURS))
            ->delete();

        $existing = PasswordResetCode::where('email', $email)->first();

        // Moins de 60 s depuis le dernier envoi : rien n'est renvoyé, et la
        // réponse reste la même.
        if ($existing !== null && $existing->resendAvailableAt()->isFuture()) {
            return;
        }

        $code = EmailCode::generate();

        PasswordResetCode::updateOrCreate(['email' => $email], EmailCode::freshAttributes($code));

        $user = User::where('email', $email)->first();

        if ($user !== null) {
            Mail::to($email)->send(new PasswordResetCodeMail($code, EmailCode::ttlMinutes()));
        }
    }

    /**
     * Change le mot de passe, révoque toutes les sessions (jetons Sanctum)
     * du compte et met fin au statut express (comme la réclamation de compte,
     * ajout v0.17). Aucune connexion automatique.
     *
     * @throws ApiException code faux, verrouillé ou expiré.
     */
    public function reset(string $email, string $code, string $password): void
    {
        $request = PasswordResetCode::where('email', $email)->first();

        // Aucune demande pour cet email : même réponse qu'un code faux.
        if ($request === null) {
            throw $this->invalidCodeException(config('email_codes.max_attempts') - 1);
        }

        if ($request->attempts_left <= 0) {
            throw $this->lockedException();
        }

        if ($request->code_expires_at->isPast()) {
            throw new ApiException('Ce code a expiré. Demandez-en un nouveau.', 422, 'reset_code_expired');
        }

        $user = User::where('email', $email)->first();

        // Sans compte, le code n'a été envoyé à personne : il est traité
        // comme faux même s'il tombait juste.
        if ($user === null || ! Hash::check($code, $request->code_hash)) {
            $request->decrement('attempts_left');

            if ($request->attempts_left <= 0) {
                throw $this->lockedException();
            }

            throw $this->invalidCodeException($request->attempts_left);
        }

        DB::transaction(function () use ($user, $request, $password) {
            // Cast `hashed` du modèle User : le mot de passe est haché à
            // l'enregistrement.
            $user->password = $password;
            $user->is_express = false;
            $user->save();

            $user->tokens()->delete();
            $request->delete();
        });
    }

    private function invalidCodeException(int $remainingAttempts): ApiException
    {
        return new ApiException('Code incorrect.', 422, 'reset_code_invalid', [
            'remaining_attempts' => $remainingAttempts,
        ]);
    }

    private function lockedException(): ApiException
    {
        return new ApiException(
            'Nombre maximal d\'essais atteint. Demandez un nouveau code.',
            422,
            'reset_code_locked',
        );
    }
}
