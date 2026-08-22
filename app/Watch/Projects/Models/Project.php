<?php

declare(strict_types=1);

namespace App\Watch\Projects\Models;

use App\Auth\Models\User;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string $token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['name'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected static function newFactory(): ProjectFactory
    {
        return ProjectFactory::new();
    }

    /**
     * The user the project belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project): void {
            $project->slug ??= Str::slug($project->name) . '-' . Str::lower(Str::random(6));
            $project->token ??= 'proj_' . Str::random(40);
        });
    }
}
