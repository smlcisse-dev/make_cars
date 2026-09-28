<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Change le mot de passe d'un compte administrateur et révoque tous ses
 * jetons Sanctum. Utile tant que « Mot de passe oublié » ne peut pas envoyer
 * d'email (domaine Resend non vérifié) — voir DEPLOIEMENT.md.
 *
 * Le mot de passe n'est jamais accepté en argument ni en option (il finirait
 * dans l'historique du shell) : il est saisi de façon masquée, deux fois.
 * La commande refuse tout compte qui n'est pas admin.
 */
class SetAdminPassword extends Command
{
    protected $signature = 'admin:set-password {email : Adresse email du compte administrateur}';

    protected $description = "Change le mot de passe d'un compte administrateur (saisie masquée) et révoque tous ses jetons";

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Aucun compte n'existe avec l'adresse {$email}.");

            return self::FAILURE;
        }

        if ($user->role !== AccountType::Admin) {
            $this->error("Le compte {$email} n'est pas un compte administrateur : cette commande ne modifie que les comptes admin.");

            return self::FAILURE;
        }

        $password = (string) $this->secret('Nouveau mot de passe');
        $confirmation = (string) $this->secret('Confirmez le nouveau mot de passe');

        if ($password !== $confirmation) {
            $this->error('Les deux saisies ne correspondent pas. Aucun changement effectué.');

            return self::FAILURE;
        }

        // Pas de ->uncompromised() : il interroge un service externe.
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]],
        );

        if ($validator->fails()) {
            $this->error('Mot de passe refusé. Aucun changement effectué.');
            foreach ($validator->errors()->get('password') as $message) {
                $this->line("  - {$message}");
            }

            return self::FAILURE;
        }

        $revoked = DB::transaction(function () use ($user, $password): int {
            $user->forceFill(['password' => $password])->save();

            return $user->tokens()->delete();
        });

        $this->info("Mot de passe du compte administrateur {$email} modifié.");
        $this->info("Jetons révoqués : {$revoked}. Toutes les sessions de ce compte sont déconnectées.");

        return self::SUCCESS;
    }
}
