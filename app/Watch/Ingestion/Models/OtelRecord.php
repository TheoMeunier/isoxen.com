<?php

declare(strict_types=1);

namespace App\Watch\Ingestion\Models;

use App\Watch\Projects\Models\Project;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared behaviour for the three `otel_*` tables.
 *
 * The tables carry `created_at` but no `updated_at`, and rows are written once by the ingestion
 * jobs and never touched again, so Eloquent's timestamp handling is off.
 */
#[WithoutTimestamps]
abstract class OtelRecord extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Keeps the sub-second precision the trace waterfall needs, which the default ISO format drops.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s.uP');
    }
}
