<?php

declare(strict_types=1);

namespace App\Watch\Projects\Actions;

use App\Watch\Projects\Models\Project;

class UpdateProjectAction
{
    /**
     * @param  array{name: string, retention_days?: int}  $payload
     */
    public function execute(Project $project, array $payload): Project
    {
        $project->update($payload);

        return $project;
    }
}
