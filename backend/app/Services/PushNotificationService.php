<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PushNotificationStatus;
use App\Enums\PushNotificationType;
use App\Enums\QuoteStatus;
use App\Models\Appointment;
use App\Models\DeviceToken;
use App\Models\Dispute;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProfessionalRegistration;
use App\Models\PushNotification;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Models\ReactivationRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Service centralisé de notifications push (CLAUDE.md §5, ajout v0.12).
 * Chaque événement métier déjà en place dans le backend appelle une méthode
 * "notifyXxx" dédiée ici plutôt que de construire lui-même le contenu de la
 * notification — même principe de centralisation que ChatService pour les
 * messages système.
 *
 * Tant que FCM n'est pas configuré (FCM_SERVER_KEY absent du .env, même
 * approche que le paiement manuel V1 — CLAUDE.md §7), l'envoi réel est
 * simulé : la notification est créée et enregistrée en base avec le statut
 * "created" ("en attente d'envoi réel"), sans jamais lever d'erreur
 * bloquante. Une fois la clé renseignée, aucune méthode ci-dessous n'a
 * besoin de changer — seul attemptDelivery() bascule d'un no-op à un envoi
 * réel.
 */
class PushNotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(User $recipient, PushNotificationType $type, string $title, ?string $body = null, array $data = []): PushNotification
    {
        $notification = PushNotification::create([
            'user_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'status' => PushNotificationStatus::Created,
        ]);

        $this->attemptDelivery($notification);

        return $notification->fresh();
    }

    /**
     * @param  iterable<User>  $recipients
     * @param  array<string, mixed>  $data
     */
    public function notifyMany(iterable $recipients, PushNotificationType $type, string $title, ?string $body = null, array $data = []): void
    {
        foreach ($recipients as $recipient) {
            $this->notify($recipient, $type, $title, $body, $data);
        }
    }

    public function markRead(PushNotification $notification): PushNotification
    {
        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $notification;
    }

    /**
     * Enregistre ou met à jour le jeton FCM de l'appareil courant — appelé
     * par l'app à chaque connexion (CLAUDE.md §5, ajout v0.12). Un même
     * jeton n'appartient jamais qu'à un seul utilisateur à la fois : un
     * changement de compte sur le même appareil réassigne le jeton plutôt
     * que d'en créer un doublon.
     */
    public function registerDeviceToken(User $user, string $token, ?string $platform): DeviceToken
    {
        return DeviceToken::updateOrCreate(
            ['token' => $token],
            ['user_id' => $user->id, 'platform' => $platform, 'last_used_at' => now()]
        );
    }

    // --- RDV (CLAUDE.md §5, ajout v0.7) ---------------------------------

    public function notifyAppointmentRequested(Appointment $appointment): void
    {
        $this->notify(
            $appointment->garage->user,
            PushNotificationType::AppointmentRequested,
            'Nouvelle demande de rendez-vous',
            'Un automobiliste souhaite prendre rendez-vous.',
            ['appointment_id' => $appointment->id]
        );
    }

    public function notifyAppointmentConfirmed(Appointment $appointment): void
    {
        $this->notify(
            $appointment->user,
            PushNotificationType::AppointmentConfirmed,
            'Rendez-vous confirmé',
            'Votre garage a confirmé votre rendez-vous.',
            ['appointment_id' => $appointment->id]
        );
    }

    public function notifyAppointmentRejected(Appointment $appointment): void
    {
        $this->notify(
            $appointment->user,
            PushNotificationType::AppointmentRejected,
            'Rendez-vous refusé',
            'Votre garage a refusé votre demande de rendez-vous.',
            ['appointment_id' => $appointment->id]
        );
    }

    public function notifyAppointmentRescheduled(Appointment $appointment): void
    {
        $this->notify(
            $appointment->user,
            PushNotificationType::AppointmentRescheduled,
            'Nouvelle date proposée',
            'Votre garage propose une autre date pour votre rendez-vous.',
            ['appointment_id' => $appointment->id]
        );
    }

    // --- Devis/Facture (CLAUDE.md §5, ajout v0.8) -----------------------

    public function notifyQuoteSent(QuoteVersion $version): void
    {
        $quote = $version->quote;

        $this->notify(
            $quote->user,
            PushNotificationType::QuoteSent,
            'Nouveau devis reçu',
            'Un devis vous a été envoyé, à consulter et valider.',
            ['quote_id' => $quote->id, 'version' => $version->version]
        );
    }

    public function notifyQuoteAccepted(QuoteVersion $version): void
    {
        $quote = $version->quote;

        $this->notify(
            $quote->garage->user,
            PushNotificationType::QuoteAccepted,
            'Devis accepté',
            'Le client a accepté votre devis.',
            ['quote_id' => $quote->id, 'version' => $version->version]
        );
    }

    public function notifyQuoteRejected(QuoteVersion $version): void
    {
        $quote = $version->quote;

        $this->notify(
            $quote->garage->user,
            PushNotificationType::QuoteRejected,
            'Devis refusé',
            'Le client a refusé votre devis.',
            ['quote_id' => $quote->id, 'version' => $version->version]
        );
    }

    public function notifyQuoteInvoiced(Quote $quote): void
    {
        $this->notify(
            $quote->user,
            PushNotificationType::QuoteInvoiced,
            'Facture disponible',
            'La facture de votre prestation est disponible.',
            ['quote_id' => $quote->id]
        );
    }

    // --- Chat (CLAUDE.md §5, ajout v0.8) ---------------------------------

    /**
     * Uniquement pour un message envoyé par une personne (sender_id non
     * nul) : un message système (génération de devis/facture) est déjà
     * couvert par sa propre notification dédiée (notifyQuoteSent(),
     * notifyQuoteInvoiced()...), pour éviter un doublon sur le même
     * événement (CLAUDE.md §5, ajout v0.12).
     */
    public function notifyNewMessage(Message $message): void
    {
        if ($message->sender_id === null) {
            return;
        }

        $conversation = $message->conversation;
        $recipient = $message->sender_id === $conversation->user_id
            ? $conversation->sellable->user
            : $conversation->user;

        $this->notify(
            $recipient,
            PushNotificationType::MessageReceived,
            'Nouveau message',
            $message->body ?? 'Vous avez reçu une nouvelle image.',
            ['conversation_id' => $conversation->id]
        );
    }

    // --- Compte professionnel (CLAUDE.md §5, ajouts v0.4/v0.6) ----------

    public function notifyRegistrationApproved(ProfessionalRegistration $registration): void
    {
        $this->notify(
            $registration->user,
            PushNotificationType::RegistrationApproved,
            'Inscription validée',
            'Votre compte professionnel a été validé, votre profil est désormais visible.',
        );
    }

    public function notifyRegistrationRejected(ProfessionalRegistration $registration): void
    {
        $this->notify(
            $registration->user,
            PushNotificationType::RegistrationRejected,
            'Inscription rejetée',
            $registration->rejection_reason,
        );
    }

    public function notifyAccountSuspended(ProfessionalRegistration $registration): void
    {
        $this->notify(
            $registration->user,
            PushNotificationType::AccountSuspended,
            'Compte suspendu',
            $registration->suspension_reason,
        );
    }

    public function notifyAccountReactivated(ProfessionalRegistration $registration): void
    {
        $this->notify(
            $registration->user,
            PushNotificationType::AccountReactivated,
            'Compte réactivé',
            'Votre compte professionnel est de nouveau visible.',
        );
    }

    /**
     * Refus d'une demande de réactivation (CLAUDE.md §5, ajout v0.28) : le
     * compte reste suspendu, le motif est transmis au professionnel.
     */
    public function notifyReactivationRequestRefused(ReactivationRequest $request): void
    {
        $this->notify(
            $request->professionalRegistration->user,
            PushNotificationType::ReactivationRequestRefused,
            'Demande de réactivation refusée',
            $request->response_reason,
            ['reactivation_request_id' => $request->id],
        );
    }

    // --- Réclamations (CLAUDE.md §5, ajout v0.11) ------------------------

    public function notifyDisputeSubmitted(Dispute $dispute): void
    {
        $this->notify(
            $dispute->respondent->user,
            PushNotificationType::DisputeSubmitted,
            'Nouvelle réclamation',
            'Un automobiliste a déposé une réclamation vous concernant.',
            ['dispute_id' => $dispute->id]
        );
    }

    public function notifyDisputeDecided(Dispute $dispute): void
    {
        $this->notify(
            $dispute->user,
            PushNotificationType::DisputeDecided,
            'Décision rendue sur votre réclamation',
            'L\'administrateur a statué sur votre réclamation.',
            ['dispute_id' => $dispute->id]
        );
    }

    // --- Commande (CLAUDE.md §5, ajout v0.9) -----------------------------

    public function notifyOrderStatusChanged(Order $order): void
    {
        $this->notify(
            $order->user,
            PushNotificationType::OrderStatusChanged,
            'Mise à jour de votre commande',
            'Le statut de votre commande a changé.',
            ['order_id' => $order->id, 'status' => $order->status->value]
        );
    }

    // --- Stock (CLAUDE.md §5, ajouts v0.4/v0.12) -------------------------

    public function notifyLowStock(Product $product): void
    {
        $this->notify(
            $product->sellable->user,
            PushNotificationType::ProductLowStock,
            'Stock bas',
            "Le stock de \"{$product->name}\" est descendu à ou sous le seuil d'alerte, pensez à réapprovisionner.",
            ['product_id' => $product->id, 'stock_quantity' => $product->stock_quantity]
        );
    }

    /**
     * Notifie uniquement les automobilistes ayant déjà au moins une
     * transaction terminée (devis facturé ou commande payée) avec ce
     * garage/cette boutique précis — jamais une diffusion à tous les
     * automobilistes de la plateforme, pour éviter le spam à grande échelle
     * (choix volontaire, CLAUDE.md §5, ajout v0.12). Piste d'amélioration
     * future : cibler aussi les clients à proximité géographique une fois la
     * recherche géolocalisée en place.
     */
    public function notifyNewProductToPastClients(Product $product): void
    {
        $sellable = $product->sellable;
        $recipients = $this->pastClients($sellable);

        $this->notifyMany(
            $recipients,
            PushNotificationType::ProductPublished,
            'Nouveau produit disponible',
            "\"{$product->name}\" est maintenant disponible chez un vendeur avec qui vous avez déjà fait affaire.",
            ['product_id' => $product->id]
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function pastClients(Garage|MarketSpaceAccount $sellable): Collection
    {
        $orderClientIds = Order::query()
            ->where('sellable_type', $sellable->getMorphClass())
            ->where('sellable_id', $sellable->id)
            ->where('status', OrderStatus::Paid)
            ->pluck('user_id');

        $quoteClientIds = $sellable instanceof Garage
            ? Quote::query()->where('garage_id', $sellable->id)->where('status', QuoteStatus::Invoiced)->pluck('user_id')
            : collect();

        $clientIds = $orderClientIds->merge($quoteClientIds)->unique();

        return User::query()->whereIn('id', $clientIds)->get();
    }

    // --- Envoi FCM (point ouvert, CLAUDE.md §7) --------------------------

    private function attemptDelivery(PushNotification $notification): void
    {
        $serverKey = config('services.fcm.server_key');

        if (blank($serverKey)) {
            return;
        }

        $tokens = $notification->user->deviceTokens()->pluck('token')->all();

        if ($tokens === []) {
            return;
        }

        try {
            Http::withHeaders([
                'Authorization' => "key={$serverKey}",
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $tokens,
                'notification' => [
                    'title' => $notification->title,
                    'body' => $notification->body,
                ],
                'data' => $notification->data ?? [],
            ])->throw();

            $notification->update(['status' => PushNotificationStatus::Sent, 'sent_at' => now()]);
        } catch (Throwable $e) {
            $notification->update([
                'status' => PushNotificationStatus::Failed,
                'failed_reason' => Str::limit($e->getMessage(), 500),
            ]);
        }
    }
}
