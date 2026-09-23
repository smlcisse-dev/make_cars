<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Exceptions\ApiException;
use App\Mail\AccountCreatedMail;
use App\Mail\VerificationCodeMail;
use App\Models\PendingProfessionalRegistration;
use App\Models\User;
use App\Support\FrontendUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inscription professionnelle courte avec vérification de l'email par code
 * (CLAUDE.md §5, ajout v0.26) : le formulaire crée seulement une demande en
 * attente ; le compte, le profil vide et le dossier `profile_incomplete`
 * ne sont créés qu'une fois le code saisi correctement. Aucun token n'est
 * émis : le professionnel se connecte ensuite normalement.
 */
class ProfessionalSignupService
{
    public function __construct(
        private readonly GarageService $garageService,
        private readonly MarketSpaceAccountService $marketSpaceAccountService,
    ) {}

    /**
     * @param  array{first_name: string, last_name: string, email: string, phone: string, password: string, account_type: string}  $data
     */
    public function start(array $data): PendingProfessionalRegistration
    {
        // Purge au fil de l'eau (aucun cron ne tourne) : les demandes
        // périmées, et toute demande précédente pour ce même email — c'est
        // ce qui rend « modifier l'email » / « recommencer » possible en
        // rappelant simplement cet endpoint.
        PendingProfessionalRegistration::query()
            ->where('email', $data['email'])
            ->orWhere(fn ($query) => $query->stale())
            ->delete();

        $code = $this->generateCode();

        $pending = PendingProfessionalRegistration::create([
            'uuid' => (string) Str::uuid(),
            'account_type' => AccountType::from($data['account_type']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            ...$this->freshCodeAttributes($code),
        ]);

        Mail::to($pending->email)->send(new VerificationCodeMail($code, config('registration.code_ttl_minutes')));

        return $pending;
    }

    /**
     * @throws ApiException demande introuvable, verrouillée, code expiré ou faux.
     * @throws ValidationException email ou téléphone pris entre-temps par un autre compte.
     */
    public function verify(string $uuid, string $code): User
    {
        $pending = $this->findActive($uuid);

        if ($pending->attempts_left <= 0) {
            throw $this->lockedException();
        }

        if ($pending->code_expires_at->isPast()) {
            throw new ApiException('Ce code a expiré. Demandez-en un nouveau.', 422, 'verification_expired');
        }

        if (! Hash::check($code, $pending->code_hash)) {
            $pending->decrement('attempts_left');

            if ($pending->attempts_left <= 0) {
                throw $this->lockedException();
            }

            throw new ApiException('Code incorrect.', 422, 'verification_code_invalid', [
                'remaining_attempts' => $pending->attempts_left,
            ]);
        }

        $user = DB::transaction(function () use ($pending) {
            $this->assertIdentityStillAvailable($pending);

            // `password` est déjà haché dans la demande : le cast `hashed`
            // de User ne re-hache pas une valeur déjà hachée, le mot de
            // passe choisi reste donc utilisable tel quel pour la connexion.
            $user = new User([
                'role' => $pending->account_type,
                'first_name' => $pending->first_name,
                'last_name' => $pending->last_name,
                'email' => $pending->email,
                'phone' => $pending->phone,
                'password' => $pending->password,
            ]);
            $user->email_verified_at = now();
            $user->save();

            $user->professionalRegistration()->create([
                'status' => RegistrationStatus::ProfileIncomplete,
            ]);

            match ($user->role) {
                AccountType::Garagiste => $this->garageService->createEmpty($user),
                AccountType::MarketSpace => $this->marketSpaceAccountService->createEmpty($user),
            };

            $pending->delete();

            return $user;
        });

        Mail::to($user->email)->send(new AccountCreatedMail($user, FrontendUrl::professionalProfile($user->role)));

        return $user;
    }

    /**
     * @throws ApiException demande introuvable, ou renvoi demandé trop tôt (429).
     */
    public function resend(string $uuid): PendingProfessionalRegistration
    {
        $pending = $this->findActive($uuid);

        $availableAt = $pending->resendAvailableAt();

        if ($availableAt->isFuture()) {
            throw new ApiException('Veuillez patienter avant de demander un nouveau code.', 429, 'resend_too_soon', [
                'retry_after_seconds' => (int) ceil(now()->diffInSeconds($availableAt, true)),
            ]);
        }

        $code = $this->generateCode();
        $pending->update($this->freshCodeAttributes($code));

        Mail::to($pending->email)->send(new VerificationCodeMail($code, config('registration.code_ttl_minutes')));

        return $pending;
    }

    /**
     * Une demande périmée est traitée comme inexistante (et supprimée au
     * passage) : un email non vérifié dans le délai ne crée jamais de compte.
     */
    private function findActive(string $uuid): PendingProfessionalRegistration
    {
        $pending = PendingProfessionalRegistration::where('uuid', $uuid)->first();

        if ($pending !== null && $pending->isStale()) {
            $pending->delete();
            $pending = null;
        }

        if ($pending === null) {
            throw new ApiException('Demande d\'inscription introuvable ou déjà utilisée.', 404, 'verification_not_found');
        }

        return $pending;
    }

    private function assertIdentityStillAvailable(PendingProfessionalRegistration $pending): void
    {
        $errors = [];

        if (User::where('email', $pending->email)->exists()) {
            $errors['email'] = ['Cet email est déjà utilisé par un autre compte.'];
        }

        if (User::where('phone', $pending->phone)->exists()) {
            $errors['phone'] = ['Ce numéro de téléphone est déjà utilisé par un autre compte.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function lockedException(): ApiException
    {
        return new ApiException(
            'Nombre maximal d\'essais atteint. Demandez un nouveau code ou modifiez votre email.',
            422,
            'verification_locked',
        );
    }

    /**
     * `random_int` (générateur cryptographique) puis complétion à gauche :
     * un code comme « 004217 » garde ses zéros en tête.
     */
    private function generateCode(): string
    {
        $length = config('registration.code_length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{code_hash: string, code_expires_at: CarbonInterface, attempts_left: int, last_code_sent_at: CarbonInterface}
     */
    private function freshCodeAttributes(string $code): array
    {
        return [
            'code_hash' => Hash::make($code),
            'code_expires_at' => now()->addMinutes(config('registration.code_ttl_minutes')),
            'attempts_left' => config('registration.max_attempts'),
            'last_code_sent_at' => now(),
        ];
    }
}
