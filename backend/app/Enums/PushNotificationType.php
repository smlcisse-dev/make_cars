<?php

namespace App\Enums;

/**
 * Catalogue fermé des événements notifiables déclenchés automatiquement par
 * PushNotificationService (CLAUDE.md §5, ajout v0.12). Toute évolution de
 * cette liste est un changement de code, pas une action d'administration
 * courante — même principe que ServiceCategory.
 */
enum PushNotificationType: string
{
    case AppointmentRequested = 'appointment_requested';
    case AppointmentConfirmed = 'appointment_confirmed';
    case AppointmentRejected = 'appointment_rejected';
    case AppointmentRescheduled = 'appointment_rescheduled';
    case QuoteSent = 'quote_sent';
    case QuoteAccepted = 'quote_accepted';
    case QuoteRejected = 'quote_rejected';
    case QuoteInvoiced = 'quote_invoiced';
    case MessageReceived = 'message_received';
    case RegistrationApproved = 'registration_approved';
    case RegistrationRejected = 'registration_rejected';
    case AccountSuspended = 'account_suspended';
    case AccountReactivated = 'account_reactivated';
    case DisputeSubmitted = 'dispute_submitted';
    case DisputeDecided = 'dispute_decided';
    case OrderStatusChanged = 'order_status_changed';
    case ProductLowStock = 'product_low_stock';
    case ProductPublished = 'product_published';
}
