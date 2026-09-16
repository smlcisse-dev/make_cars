<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Garage;
use App\Models\Message;
use App\Models\QuoteVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Une conversation regroupe tous les échanges entre UN garage et UN
 * automobiliste, pas rattachée à un RDV précis — un automobiliste peut
 * contacter un garage à tout moment (CLAUDE.md §5, ajout v0.8).
 */
class ChatService
{
    private function disk(): string
    {
        return config('filesystems.private_media_disk', 'local');
    }

    public function findOrCreateConversation(Garage $garage, User $automobiliste): Conversation
    {
        return Conversation::firstOrCreate([
            'garage_id' => $garage->id,
            'user_id' => $automobiliste->id,
        ]);
    }

    /**
     * @param  array{body: ?string}  $data
     */
    public function sendMessage(Conversation $conversation, User $sender, array $data, ?UploadedFile $image): Message
    {
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => $data['body'] ?? null,
        ]);

        if ($image) {
            $disk = $this->disk();
            $path = $image->store('chat-attachments/'.$conversation->id, $disk);
            $message->update(['image_disk' => $disk, 'image_path' => $path]);
        }

        $conversation->update(['last_message_at' => $message->created_at]);

        return $message;
    }

    /**
     * Message système posté automatiquement à chaque génération de
     * devis/facture, avec le PDF en pièce jointe (CLAUDE.md §5, ajout v0.8).
     * Ne passe jamais par du texte libre interprété : le lien reste une
     * référence structurée vers la QuoteVersion, téléchargée via l'endpoint
     * devis dédié (pas de duplication du fichier).
     */
    public function postQuoteVersionMessage(Garage $garage, User $automobiliste, QuoteVersion $quoteVersion, string $body): Message
    {
        $conversation = $this->findOrCreateConversation($garage, $automobiliste);

        $message = $conversation->messages()->create([
            'sender_id' => null,
            'body' => $body,
            'attachment_type' => Message::ATTACHMENT_QUOTE_PDF,
            'quote_version_id' => $quoteVersion->id,
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);

        return $message;
    }
}
