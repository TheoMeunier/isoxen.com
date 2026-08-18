<?php

declare(strict_types=1);

namespace App\Watch\Actions;

use App\Watch\Models\Project;

class DeleteProjectAction
{
    public function execute(Project $project): void
    {
        $project->delete();
    }
}
