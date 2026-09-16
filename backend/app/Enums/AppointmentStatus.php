<?php

namespace App\Enums;

/**
 * Cycle de vie d'un RDV (CLAUDE.md §5, ajout v0.7). "Completed" est prévu
 * pour anticiper la chaîne RDV → devis → prestation (règle 10) mais n'est
 * pas encore atteignable via ce module — la transition sera déclenchée par
 * le futur module Devis/Prestation.
 */
enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
