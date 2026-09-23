<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Profil et informations légales avant la validation admin (CLAUDE.md §5,
     * ajout v0.26) :
     * - IFU, NPI et date de soumission sur le dossier KYC (privés) ;
     * - chaque dossier existant sans profil reçoit son profil Garage/Market
     *   Space (nom, adresse, téléphone) AVANT la suppression de
     *   `structure_name`/`address` : le profil devient leur seule source ;
     * - les dossiers `pending` passent à `profile_incomplete` (il leur manque
     *   IFU et NPI) ; `approved`/`rejected` sont inchangés — un compte déjà
     *   approuvé sans IFU/NPI garde son accès, pas de blocage rétroactif.
     */
    public function up(): void
    {
        Schema::table('professional_registrations', function (Blueprint $table) {
            $table->string('ifu')->nullable()->after('business_registration_number');
            $table->string('npi')->nullable()->after('ifu');
            $table->timestamp('submitted_at')->nullable()->after('status');
        });

        $now = now();

        $registrationsWithoutProfile = DB::table('professional_registrations')
            ->join('users', 'users.id', '=', 'professional_registrations.user_id')
            ->leftJoin('garages', 'garages.user_id', '=', 'users.id')
            ->leftJoin('market_space_accounts', 'market_space_accounts.user_id', '=', 'users.id')
            ->whereNull('garages.id')
            ->whereNull('market_space_accounts.id')
            ->whereIn('users.role', ['garagiste', 'market_space'])
            ->select('users.id as user_id', 'users.role', 'users.phone', 'professional_registrations.structure_name', 'professional_registrations.address')
            ->get();

        foreach ($registrationsWithoutProfile as $registration) {
            DB::table($registration->role === 'garagiste' ? 'garages' : 'market_space_accounts')->insert([
                'user_id' => $registration->user_id,
                'name' => $registration->structure_name,
                'address' => $registration->address,
                'phone' => $registration->phone,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('professional_registrations', function (Blueprint $table) {
            $table->dropColumn(['structure_name', 'address']);
        });

        DB::table('professional_registrations')
            ->where('status', 'pending')
            ->update(['status' => 'profile_incomplete', 'updated_at' => $now]);
    }

    /**
     * Retour arrière partiel, commenté :
     * - `structure_name`/`address` sont recréés (nullable) et recopiés depuis
     *   le profil ;
     * - `profile_incomplete` redevient `pending` (sans pouvoir distinguer les
     *   dossiers qui étaient `pending` avant cette migration de ceux créés
     *   par le nouveau parcours) ;
     * - les profils créés par up() sont conservés : rien ne les distingue
     *   d'un profil créé normalement, et l'ancienne approbation ne recrée
     *   pas un profil déjà existant ;
     * - IFU, NPI et date de soumission sont perdus.
     */
    public function down(): void
    {
        Schema::table('professional_registrations', function (Blueprint $table) {
            $table->string('structure_name')->nullable()->after('user_id');
            $table->string('address')->nullable()->after('structure_name');
        });

        foreach (['garagiste' => 'garages', 'market_space' => 'market_space_accounts'] as $role => $profileTable) {
            $profiles = DB::table($profileTable)
                ->join('users', 'users.id', '=', "{$profileTable}.user_id")
                ->where('users.role', $role)
                ->select("{$profileTable}.user_id", "{$profileTable}.name", "{$profileTable}.address")
                ->get();

            foreach ($profiles as $profile) {
                DB::table('professional_registrations')
                    ->where('user_id', $profile->user_id)
                    ->update(['structure_name' => $profile->name, 'address' => $profile->address]);
            }
        }

        DB::table('professional_registrations')
            ->where('status', 'profile_incomplete')
            ->update(['status' => 'pending']);

        Schema::table('professional_registrations', function (Blueprint $table) {
            $table->dropColumn(['ifu', 'npi', 'submitted_at']);
        });
    }
};
