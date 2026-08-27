<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A record that one Calendly resource has been pulled into this app.
 *
 * @property int $id
 * @property string $calendly_uri
 * @property string $resource_type
 * @property string|null $importable_type
 * @property int|null $importable_id
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $imported_at
 */
#[Fillable(['calendly_uri', 'resource_type', 'importable_type', 'importable_id', 'payload', 'imported_at'])]
class CalendlyImport extends Model
{
    /**
     * Get the local record this Calendly resource became.
     *
     * @return MorphTo<Model, $this>
     */
    public function importable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'imported_at' => 'datetime',
        ];
    }
}
