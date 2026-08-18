<?php

declare(strict_types=1);

namespace App\Watch\Projects\Actions;

use App\Auth\Models\User;
use App\Watch\Projects\Models\Project;

class CreateProjectAction
{
    /**
     * @param array{name: string} $payload
     */
    public function execute(User $user, array $payload): Project
    {
        return $user->projects()->create($payload);
    }
}
