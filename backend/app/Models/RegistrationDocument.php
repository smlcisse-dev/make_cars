<?php

namespace App\Models;

use App\Enums\RegistrationDocumentType;
use Database\Factories\RegistrationDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'disk', 'path'])]
class RegistrationDocument extends Model
{
    /** @use HasFactory<RegistrationDocumentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RegistrationDocumentType::class,
        ];
    }

    /**
     * @return BelongsTo<ProfessionalRegistration, $this>
     */
    public function professionalRegistration(): BelongsTo
    {
        return $this->belongsTo(ProfessionalRegistration::class);
    }
}
