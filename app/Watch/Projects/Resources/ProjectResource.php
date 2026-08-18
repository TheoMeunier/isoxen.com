<?php

declare(strict_types=1);

namespace App\Watch\Projects\Resources;

use App\Watch\Projects\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * Transform the project into an array for the frontend.
     *
     * The ingestion token is a secret used to authenticate incoming data, so
     * it's only resolved to its real value on the project's own page. On
     * every other page the key is still present, but null, so the frontend
     * can rely on a single, consistent `Project` shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'slug'      => $this->slug,
            'token'     => $request->routeIs('projects.show') ? $this->token : null,
            'createdAt' => $this->created_at,
        ];
    }
}
