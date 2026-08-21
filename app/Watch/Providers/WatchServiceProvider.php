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

        // isoxen.com monitors itself (see the "jobs" sensor's comment in
        // packages/isoxen-client/config/isoxen.php), so this is both the
        // client library's usage example and what makes our own Users tab
        // show a name/email instead of just an id. Off wherever
        // `isoxen.user_context` is disabled -- same gate as every other
        // span/log carrying user context.
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
