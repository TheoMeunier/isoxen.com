<?php

namespace App\Core\Middleware;

use App\Watch\Ingestion\Queries\SpanTypeCountsQuery;
use App\Watch\Ingestion\Support\ObservabilityCategories;
use App\Watch\Projects\Models\Project;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            ...$this->projectContext($request),
        ];
    }

    /**
     * Share the project being viewed, plus its categories and counts, so the sidebar can build its nav.
     *
     * @return array<string, mixed>
     */
    private function projectContext(Request $request): array
    {
        $project = $request->route('project');

        if (! $project instanceof Project) {
            return [];
        }

        if ($request->user()?->cannot('view', $project) !== false) {
            return [];
        }

        return [
            'currentProject' => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
            ],
            'categoryCounts' => app(SpanTypeCountsQuery::class)->execute($project),
            'observabilityCategories' => ObservabilityCategories::all(),
        ];
    }
}
