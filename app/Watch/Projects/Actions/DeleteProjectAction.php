<?php

declare(strict_types=1);

namespace App\Watch\Projects\Actions;

use App\Watch\Projects\Models\Project;

class DeleteProjectAction
{
    public function execute(Project $project): void
    {
        $project->delete();
    }
}
