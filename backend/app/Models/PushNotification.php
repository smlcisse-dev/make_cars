<?php

namespace App\Models;

use App\Enums\PushNotificationStatus;
use App\Enums\PushNotificationType;
use Database\Factories\PushNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Événement notifiable enregistré pour un destinataire précis, indépendamment
 * de l'envoi FCM réel (CLAUDE.md §5, ajout v0.12). Nommé "PushNotification"
 * (table dédiée "push_notifications") pour ne pas entrer en collision avec le
 * système de notifications intégré de Laravel (trait Notifiable de User,
 * table conventionnelle "notifications") — les deux restent indépendants.
 */
#[Fillable(['user_id', 'type', 'title', 'body', 'data', 'status', 'read_at', 'sent_at', 'failed_reason'])]
class PushNotification extends Model
{
    /** @use HasFactory<PushNotificationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PushNotificationType::class,
            'data' => 'array',
            'status' => PushNotificationStatus::class,
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
