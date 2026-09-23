<?php

namespace Database\Seeders\Concerns;

use App\Enums\AccountType;
use App\Enums\RegistrationDocumentType;
use App\Enums\RegistrationStatus;
use App\Models\Arrondissement;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\OrderLine;
use App\Models\QuoteLine;
use App\Models\User;
use App\Services\GarageService;
use App\Services\MarketSpaceAccountService;
use App\Services\ProfessionalRegistrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Comptes professionnels de test suivant le parcours v0.26 (CLAUDE.md §5) :
 * compte au dossier `profile_incomplete` avec profil vide (état atteint après
 * la vérification de l'email), puis profil public, informations légales,
 * soumission et décision admin via les services réels. Seule la
 * vérification par code est court-circuitée (pas d'email à lire dans un
 * seeder) : le compte est créé directement, email déjà vérifié.
 *
 * Un compte existant n'est jamais supprimé s'il porte une trace réelle
 * (CLAUDE.md §6, traçabilité) : un compte approuvé est toujours conservé
 * tel quel, un compte représentant un état d'inscription n'est réinitialisé
 * que s'il n'a aucun historique d'activité (voir professionalActivity()).
 *
 * Suppose que la classe utilisatrice utilise aussi GeneratesFakeKycDocuments.
 */
trait SeedsProfessionalAccounts
{
    /**
     * Issue de chaque compte traité, par email, pour le résumé en console.
     *
     * @var array<string, string>
     */
    private array $seededAccountOutcomes = [];

    /**
     * Crée un compte professionnel au statut demandé. Mot de passe : « password ».
     *
     * Compte déjà existant : conservé tel quel s'il est demandé au statut
     * approuvé ou s'il a un historique d'activité (avertissement en
     * console) ; sinon supprimé puis recréé, le tout dans une transaction.
     *
     * @param  array{structure_name: string, address: string, neighborhood: string, image_directory: string}|null  $profile  null = profil laissé vide
     */
    private function seedProfessionalAccount(
        AccountType $accountType,
        string $email,
        string $firstName,
        string $lastName,
        string $phone,
        RegistrationStatus $status,
        ?array $profile,
        User $admin,
        ?string $rejectionReason = null,
    ): User {
        $existing = User::where('email', $email)->first();

        if ($existing && $status === RegistrationStatus::Approved) {
            $this->seededAccountOutcomes[$email] = 'conservé (compte approuvé, jamais réinitialisé)';

            return $existing;
        }

        if ($existing && ($activity = $this->professionalActivity($existing)) !== []) {
            $this->seededAccountOutcomes[$email] = 'conservé (historique d\'activité)';
            $this->command?->warn(
                "{$email} n'a pas été réinitialisé : il a un historique d'activité (".implode(', ', $activity).'). '.
                'Il est laissé tel quel, dans son état actuel.',
            );

            return $existing;
        }

        $oldDocuments = $existing?->professionalRegistration?->documents
            ->map(fn ($document) => [$document->disk, $document->path])->all() ?? [];

        $user = DB::transaction(function () use ($existing, $accountType, $email, $firstName, $lastName, $phone, $status, $profile, $admin, $rejectionReason) {
            if ($existing) {
                $existing->professionalProfile()?->products()->delete();
                $existing->delete();
            }

            return $this->createProfessionalAccount($accountType, $email, $firstName, $lastName, $phone, $status, $profile, $admin, $rejectionReason);
        });

        // Fichiers physiques des anciens justificatifs, que la suppression en
        // base n'efface pas : seulement une fois la transaction validée.
        foreach ($oldDocuments as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }

        $this->seededAccountOutcomes[$email] = $existing ? 'réinitialisé' : 'créé';

        return $user;
    }

    /**
     * Historique d'activité réelle d'un compte professionnel : tout ce qui
     * interdit de le supprimer (la suppression du profil effacerait en
     * cascade RDV, devis et conversations, et laisserait orphelins
     * commandes, avis et réclamations). Vide = aucun historique.
     *
     * @return list<string>
     */
    private function professionalActivity(User $user): array
    {
        $profile = $user->professionalProfile();

        if (! $profile) {
            return [];
        }

        $productIds = $profile->products()->pluck('id');

        $counts = [
            'produits vendus en commande' => OrderLine::whereIn('product_id', $productIds)->count(),
            'produits cités dans un devis' => QuoteLine::whereIn('product_id', $productIds)->count(),
            'commandes' => $profile->orders()->count(),
            'conversations' => $profile->conversations()->count(),
            'avis' => $profile->reviews()->count(),
            'réclamations' => $profile->disputes()->count(),
        ];

        if ($profile instanceof Garage) {
            $counts['devis'] = $profile->quotes()->count();
            $counts['rendez-vous'] = $profile->appointments()->count();
        }

        return array_values(array_map(
            fn (string $label, int $count) => "{$count} {$label}",
            array_keys(array_filter($counts)),
            array_filter($counts),
        ));
    }

    private function createProfessionalAccount(
        AccountType $accountType,
        string $email,
        string $firstName,
        string $lastName,
        string $phone,
        RegistrationStatus $status,
        ?array $profile,
        User $admin,
        ?string $rejectionReason,
    ): User {
        $user = new User([
            'role' => $accountType,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('password'),
        ]);
        $user->email_verified_at = now();
        $user->save();

        $registration = $user->professionalRegistration()->create(['status' => RegistrationStatus::ProfileIncomplete]);
        $profileModel = $accountType === AccountType::MarketSpace
            ? app(MarketSpaceAccountService::class)->createEmpty($user)
            : app(GarageService::class)->createEmpty($user);

        if ($profile === null) {
            return $user;
        }

        $this->completePublicProfile($profileModel, $profile);

        $registrationService = app(ProfessionalRegistrationService::class);
        $registrationService->updateLegalInfo($registration, [
            'business_registration_number' => 'RB/COT/24 B '.random_int(10000, 99999),
            // IFU (13 chiffres) et NPI (10 chiffres) fictifs mais au bon format.
            'ifu' => '32024'.random_int(10000000, 99999999),
            'npi' => (string) random_int(1000000000, 9999999999),
        ]);
        $registrationService->replaceLegalDocument($registration, RegistrationDocumentType::BusinessRegistration, $this->fakeBusinessRegistrationDocument());
        $registrationService->replaceLegalDocument($registration, RegistrationDocumentType::IdentityCertificate, $this->fakeIdentityCertificateDocument());

        if ($status === RegistrationStatus::ProfileIncomplete) {
            return $user;
        }

        $registrationService->submit($registration);

        match ($status) {
            RegistrationStatus::Approved => $registrationService->approve($registration, $admin),
            RegistrationStatus::Rejected => $registrationService->reject($registration, $admin, $rejectionReason ?? 'Dossier incomplet.'),
            default => null,
        };

        return $user->fresh();
    }

    /**
     * Profil public complet (CLAUDE.md §5, ajout v0.20) : photo à chemin fixe,
     * écrasée à chaque passage plutôt qu'accumulée.
     *
     * @param  array{structure_name: string, address: string, neighborhood: string, image_directory: string}  $data
     */
    private function completePublicProfile(Garage|MarketSpaceAccount $profile, array $data): void
    {
        $arrondissement = Arrondissement::query()->with('commune')->orderBy('id')->firstOrFail();

        $profile->update([
            'name' => $data['structure_name'],
            'address' => $data['address'],
            'latitude' => 6.3654,
            'longitude' => 2.4183,
            'department_id' => $arrondissement->commune->department_id,
            'commune_id' => $arrondissement->commune_id,
            'arrondissement_id' => $arrondissement->id,
            'neighborhood' => $data['neighborhood'],
        ]);

        $profile->openingHours()->delete();
        $profile->openingHours()->createMany(
            array_map(fn (int $day) => [
                'day_of_week' => $day,
                'is_closed' => $day === 7,
                'opens_at' => $day === 7 ? null : '08:00',
                'closes_at' => $day === 7 ? null : '18:00',
            ], range(1, 7)),
        );

        $profile->images()->delete();
        $profile->images()->create([
            'disk' => 'public',
            'path' => $this->fakeCatalogImage('photo.jpg')->storeAs($data['image_directory'], 'photo.jpg', 'public'),
            'position' => 1,
        ]);
    }
}
