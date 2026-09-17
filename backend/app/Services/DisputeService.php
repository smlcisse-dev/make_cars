<?php

namespace App\Services;

use App\Enums\DisputeResolutionAction;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\QuoteStatus;
use App\Mail\DisputeDecisionMail;
use App\Models\Dispute;
use App\Models\DisputeMessage;
use App\Models\Garage;
use App\Models\Order;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Une réclamation n'est possible que sur une transaction terminée — un
 * devis facturé ou une commande payée, même principe que le module Avis
 * (CLAUDE.md §5, ajout v0.11).
 */
class DisputeService
{
    public function __construct(
        private readonly ProfessionalRegistrationService $registrationService,
    ) {}

    private function disk(): string
    {
        return config('filesystems.private_media_disk', 'local');
    }

    /**
     * @param  array{reason: string}  $data
     * @param  UploadedFile[]  $photos
     */
    public function createForQuote(Quote $quote, User $client, array $data, array $photos): Dispute
    {
        abort_unless($quote->user_id === $client->id, 404);
        abort_unless($quote->status === QuoteStatus::Invoiced, 403, 'Seul un devis facturé peut faire l\'objet d\'une réclamation.');

        return $this->store(Garage::class, $quote->garage_id, Quote::class, $quote->id, $client, $data, $photos);
    }

    /**
     * @param  array{reason: string}  $data
     * @param  UploadedFile[]  $photos
     */
    public function createForOrder(Order $order, User $client, array $data, array $photos): Dispute
    {
        abort_unless($order->user_id === $client->id, 404);
        abort_unless($order->status === OrderStatus::Paid, 403, 'Seule une commande payée peut faire l\'objet d\'une réclamation.');

        return $this->store($order->sellable_type, $order->sellable_id, Order::class, $order->id, $client, $data, $photos);
    }

    /**
     * @param  array{reason: string}  $data
     * @param  UploadedFile[]  $photos
     */
    private function store(string $respondentType, int $respondentId, string $transactionType, int $transactionId, User $client, array $data, array $photos): Dispute
    {
        return DB::transaction(function () use ($respondentType, $respondentId, $transactionType, $transactionId, $client, $data, $photos) {
            $dispute = Dispute::create([
                'respondent_type' => $respondentType,
                'respondent_id' => $respondentId,
                'transaction_type' => $transactionType,
                'transaction_id' => $transactionId,
                'user_id' => $client->id,
                'reason' => $data['reason'],
                'status' => DisputeStatus::Submitted,
            ]);

            $disk = $this->disk();
            foreach (array_values($photos) as $position => $photo) {
                $path = $photo->store('dispute-attachments/'.$dispute->id, $disk);
                $dispute->attachments()->create(['disk' => $disk, 'path' => $path, 'position' => $position]);
            }

            return $dispute->fresh('attachments');
        });
    }

    /**
     * L'admin peut demander une réponse/défense au professionnel — passe le
     * dossier en instruction. Optionnelle : l'admin peut aussi trancher
     * directement si les preuves jointes sont suffisantes.
     */
    public function requestResponse(Dispute $dispute, User $admin, ?string $message): Dispute
    {
        $dispute->update([
            'status' => DisputeStatus::UnderReview,
            'response_requested_at' => now(),
            'response_requested_by' => $admin->id,
        ]);

        if ($message !== null) {
            $dispute->messages()->create(['author_id' => $admin->id, 'body' => $message]);
        }

        return $dispute->fresh();
    }

    /**
     * Réponse du professionnel (ou relance de l'admin) dans l'espace
     * d'échange dédié à la réclamation. Une première réponse fait passer le
     * dossier en instruction même sans demande formelle préalable.
     */
    public function respond(Dispute $dispute, User $author, string $body): DisputeMessage
    {
        $message = $dispute->messages()->create(['author_id' => $author->id, 'body' => $body]);

        if ($dispute->status === DisputeStatus::Submitted) {
            $dispute->update(['status' => DisputeStatus::UnderReview]);
        }

        return $message;
    }

    public function resolveRejected(Dispute $dispute, User $admin, string $reason): Dispute
    {
        $dispute->update([
            'status' => DisputeStatus::ResolvedRejected,
            'resolution_reason' => $reason,
            'decided_by' => $admin->id,
            'decided_at' => now(),
        ]);

        $this->notifyClient($dispute->fresh());

        return $dispute->fresh();
    }

    /**
     * Aucune sanction automatique : l'admin choisit librement la suite selon
     * la gravité qu'il évalue. "Suspension" réutilise le mécanisme déjà
     * construit (CLAUDE.md §5, ajout v0.6) ; "Warning" n'a d'autre effet que
     * sa trace sur le dossier lui-même.
     */
    public function resolveFounded(Dispute $dispute, User $admin, string $reason, DisputeResolutionAction $action): Dispute
    {
        return DB::transaction(function () use ($dispute, $admin, $reason, $action) {
            $dispute->update([
                'status' => DisputeStatus::ResolvedFounded,
                'resolution_reason' => $reason,
                'resolution_action' => $action,
                'decided_by' => $admin->id,
                'decided_at' => now(),
            ]);

            if ($action === DisputeResolutionAction::Suspension) {
                $registration = $dispute->respondent->user->professionalRegistration;
                abort_if($registration === null, 422, 'Inscription introuvable pour ce compte.');

                $this->registrationService->suspend($registration, $admin, $reason);
            }

            $dispute = $dispute->fresh();
            $this->notifyClient($dispute);

            return $dispute;
        });
    }

    /**
     * Pas clôturée automatiquement : la clôture est un pas distinct, que
     * l'admin déclenche une fois la décision (et son éventuelle suite)
     * actée.
     */
    public function close(Dispute $dispute): Dispute
    {
        $dispute->update([
            'status' => DisputeStatus::Closed,
            'closed_at' => now(),
        ]);

        return $dispute;
    }

    /**
     * Seul canal disponible pour notifier l'automobiliste de la décision
     * finale, en l'absence de notifications push (CLAUDE.md §7) et de chat
     * Admin↔Automobiliste.
     */
    private function notifyClient(Dispute $dispute): void
    {
        $client = $dispute->user;

        if ($client->email === null) {
            return;
        }

        Mail::to($client->email)->send(new DisputeDecisionMail($dispute));
    }
}
