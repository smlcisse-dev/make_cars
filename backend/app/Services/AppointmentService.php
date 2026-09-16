<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Garage;
use App\Models\RepairService;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Premier maillon de la chaîne RDV → devis → validation → prestation →
 * paiement → facture (CLAUDE.md §5, règle 10 et ajout v0.7). Aucune méthode
 * de cette classe ne doit jamais déclencher un paiement — la prise de RDV
 * n'en déclenche aucun, c'est une exigence explicite du cahier des charges.
 */
class AppointmentService
{
    /**
     * @param  array{description: ?string, requested_at: string}  $data
     */
    public function create(User $automobiliste, Garage $garage, ?RepairService $repairService, array $data): Appointment
    {
        return Appointment::create([
            'garage_id' => $garage->id,
            'user_id' => $automobiliste->id,
            'repair_service_id' => $repairService?->id,
            'description' => $data['description'] ?? null,
            'requested_at' => $data['requested_at'],
            'status' => AppointmentStatus::Pending,
        ]);
    }

    /**
     * Le garagiste confirme la date/heure demandée telle quelle.
     */
    public function confirm(Appointment $appointment): Appointment
    {
        $appointment->update([
            'status' => AppointmentStatus::Confirmed,
            'confirmed_at' => $appointment->requested_at,
        ]);

        return $appointment;
    }

    public function reject(Appointment $appointment, ?string $reason): Appointment
    {
        $appointment->update([
            'status' => AppointmentStatus::Rejected,
            'rejection_reason' => $reason,
        ]);

        return $appointment;
    }

    /**
     * Contre-proposition du garagiste : en attente de la réponse du client
     * (acceptReject() ou cancel()) — pas de créneaux prédéfinis en V1.
     */
    public function reschedule(Appointment $appointment, CarbonInterface $proposedAt): Appointment
    {
        $appointment->update([
            'status' => AppointmentStatus::Rescheduled,
            'proposed_at' => $proposedAt,
        ]);

        return $appointment;
    }

    /**
     * Le client accepte la date proposée par le garagiste.
     */
    public function acceptReschedule(Appointment $appointment): Appointment
    {
        $appointment->update([
            'status' => AppointmentStatus::Confirmed,
            'confirmed_at' => $appointment->proposed_at,
        ]);

        return $appointment;
    }

    /**
     * Annulation par le client — possible tant que le RDV n'est ni terminé
     * ni déjà refusé/annulé. Un client qui ne veut pas de la contre-proposition
     * du garagiste (statut Rescheduled) annule plutôt que de la refuser
     * explicitement : ça referme la négociation simplement (V1).
     */
    public function cancel(Appointment $appointment): Appointment
    {
        $appointment->update(['status' => AppointmentStatus::Cancelled]);

        return $appointment;
    }
}
