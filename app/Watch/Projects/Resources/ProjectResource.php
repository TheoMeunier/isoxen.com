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
     * Transform the project for the frontend; the secret token resolves only on the project's own page.
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
