<?php

namespace App\Models;

use Database\Factories\DeviceTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeton FCM d'un appareil, mis à jour par l'app à chaque connexion
 * (CLAUDE.md §5, ajout v0.12). Un utilisateur peut avoir plusieurs
 * appareils ; un jeton donné n'appartient jamais qu'à un seul utilisateur à
 * la fois (contrainte unique sur "token").
 */
#[Fillable(['user_id', 'token', 'platform', 'last_used_at'])]
class DeviceToken extends Model
{
    /** @use HasFactory<DeviceTokenFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
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
