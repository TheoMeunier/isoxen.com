<?php

declare(strict_types=1);

namespace App\Watch\Providers;

use App\Auth\Models\User;
use App\Watch\Ingestion\Console\IngestionStatusCommand;
use App\Watch\Projects\Models\Project;
use App\Watch\Projects\Policies\ProjectPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Isoxen\Client\Facades\OpenTelemetry;

class WatchServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);

        OpenTelemetry::user(fn (User $user): array => [
            'user.id'    => $user->id,
            'user.email' => $user->email,
            'user.name'  => $user->name,
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([IngestionStatusCommand::class]);
        }
    }
}
