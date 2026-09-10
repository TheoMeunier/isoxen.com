<?php

declare(strict_types=1);

namespace App\Watch\Projects\Actions;

use App\Watch\Projects\Models\Project;

class UpdateProjectAction
{
    /**
     * @param  array{name: string, alert_error_rate_threshold?: int|null}  $payload
     */
    public function execute(Project $project, array $payload): Project
    {
        $project->update($payload);

        return $project;
    }
}
