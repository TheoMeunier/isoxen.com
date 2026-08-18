<?php

declare(strict_types=1);

namespace App\Watch\Actions;

use App\Watch\Models\Project;

class UpdateProjectAction
{
    /**
     * @param array{name: string} $payload
     */
    public function execute(Project $project, array $payload): Project
    {
        $project->update($payload);

        return $project;
    }
}
