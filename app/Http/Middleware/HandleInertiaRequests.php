<?php

namespace App\Http\Middleware;

use App\Models\DatabaseConnection;
use App\Support\Database\ActiveRemoteDatabaseContext;
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
            'activeDatabaseContext' => fn () => $request->user()
                ? ActiveRemoteDatabaseContext::forInertiaShared()
                : null,
            'databaseConnectionsForSelector' => function () use ($request) {
                if ($request->user() === null) {
                    return [];
                }

                return DatabaseConnection::query()
                    ->where('is_active', true)
                    ->orderBy('connection_group')
                    ->orderBy('slug')
                    ->get()
                    ->map(fn (DatabaseConnection $c): array => [
                        'id' => $c->id,
                        'slug' => $c->slug,
                        'label' => $c->label,
                        'driver' => $c->driver->value,
                        'connection_group' => $c->connection_group,
                        'access_mode' => $c->access_mode->value,
                    ])
                    ->all();
            },
        ];
    }
}
