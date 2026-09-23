<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Models\User;
use Database\Seeders\Concerns\GeneratesFakeKycDocuments;
use Database\Seeders\Concerns\SeedsProfessionalAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Mail;

/**
 * Jeu de données de test pour le parcours d'inscription professionnelle
 * (CLAUDE.md §5, ajout v0.26). Non appelé depuis DatabaseSeeder — se
 * déclenche manuellement :
 *
 *   php artisan db:seed --class=ProfessionalRegistrationTestSeeder
 *
 * - garage.nouveau.incomplete : Garagiste `profile_incomplete`, profil vide ;
 * - garage.etoile.pending : Garagiste `pending`, profil et informations
 *   légales complets (pour tester l'approbation) ;
 * - pieces.express.pending : Market Space `pending` complet ;
 * - garage.excellence.approved : Garagiste approuvé, profil complet ;
 * - auto.pieces.rejected : Market Space `rejected` avec motif.
 * Le Market Space approuvé (marche.pieces.approved) est créé par
 * CatalogTestSeeder. Tous les mots de passe : « password ».
 *
 * Idempotent sans jamais effacer d'historique (CLAUDE.md §6) : le compte
 * approuvé est créé s'il manque, jamais réinitialisé ; les comptes d'état
 * d'inscription sont supprimés puis recréés à l'identique uniquement s'ils
 * n'ont aucune activité (sinon conservés, avec un avertissement) — voir
 * SeedsProfessionalAccounts.
 * Aucun email réel n'est envoyé (Mail::fake) : les décisions admin passent
 * par le service réel, qui en envoie normalement.
 */
class ProfessionalRegistrationTestSeeder extends Seeder
{
    use GeneratesFakeKycDocuments, SeedsProfessionalAccounts;

    public function run(): void
    {
        Mail::fake();

        $admin = User::where('role', AccountType::Admin)->first()
            ?? User::factory()->admin()->create([
                'name' => 'Admin Make Cars',
                'email' => 'admin@makecars.test',
            ]);

        $this->seedProfessionalAccount(
            AccountType::Garagiste, 'garage.nouveau.incomplete@makecars.test', 'Koffi', 'Houngbédji',
            '+22901900101'.random_int(10, 99), RegistrationStatus::ProfileIncomplete, null, $admin,
        );

        $this->seedProfessionalAccount(
            AccountType::Garagiste, 'garage.etoile.pending@makecars.test', 'Moussa', 'Adéchi',
            '+22901900102'.random_int(10, 99), RegistrationStatus::Pending,
            $this->profileData('Garage Étoile du Bénin', 'Quartier Akpakpa, Cotonou, Bénin', 'Akpakpa', 'garages/registration-test-seeder/etoile'),
            $admin,
        );

        $this->seedProfessionalAccount(
            AccountType::MarketSpace, 'pieces.express.pending@makecars.test', 'Aïcha', 'Sanni',
            '+22901900203'.random_int(10, 99), RegistrationStatus::Pending,
            $this->profileData('Pièces Express Cotonou', 'Quartier Gbégamey, Cotonou, Bénin', 'Gbégamey', 'market-space/registration-test-seeder/express'),
            $admin,
        );

        $this->seedProfessionalAccount(
            AccountType::Garagiste, 'garage.excellence.approved@makecars.test', 'Rodrigue', 'Zinsou',
            '+22901900104'.random_int(10, 99), RegistrationStatus::Approved,
            $this->profileData('Garage Excellence Akpakpa', 'Quartier Akpakpa, Cotonou, Bénin', 'Akpakpa', 'garages/registration-test-seeder/excellence'),
            $admin,
        );

        $this->seedProfessionalAccount(
            AccountType::MarketSpace, 'auto.pieces.rejected@makecars.test', 'Florent', 'Dossou',
            '+22901900205'.random_int(10, 99), RegistrationStatus::Rejected,
            $this->profileData('Auto Pièces Fidjrossè', 'Quartier Fidjrossè, Cotonou, Bénin', 'Fidjrossè', 'market-space/registration-test-seeder/fidjrosse'),
            $admin,
            "Le document du registre de commerce fourni est illisible et les photos ne correspondent pas à l'adresse déclarée. Merci de corriger votre dossier puis de le soumettre à nouveau.",
        );

        foreach ($this->seededAccountOutcomes as $email => $outcome) {
            $this->command?->info("ProfessionalRegistrationTestSeeder : {$email} — {$outcome}.");
        }
    }

    /**
     * @return array{structure_name: string, address: string, neighborhood: string, image_directory: string}
     */
    private function profileData(string $structureName, string $address, string $neighborhood, string $imageDirectory): array
    {
        return ['structure_name' => $structureName, 'address' => $address, 'neighborhood' => $neighborhood, 'image_directory' => $imageDirectory];
    }
}
