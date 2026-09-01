<?php

namespace Thevps\Kanban;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class KanbanServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/kanban.php', 'kanban');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/kanban.php' => config_path('kanban.php'),
            ], 'laravel-kanban-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'laravel-kanban-migrations');

            // Vue/TS components published straight into the host's resources/js tree — the
            // host owns the copies and adapts UI-kit imports (radix-vue vs reka-ui) to its
            // own stack. See README "Adaptation points".
            $this->publishes([
                __DIR__.'/../resources/js/components/kanban' => resource_path('js/components/kanban'),
                __DIR__.'/../resources/js/pages/kanban' => resource_path('js/pages/kanban'),
                __DIR__.'/../resources/js/lib/kanbanColors.ts' => resource_path('js/lib/kanbanColors.ts'),
                __DIR__.'/../resources/js/lib/priority.ts' => resource_path('js/lib/priority.ts'),
                __DIR__.'/../resources/js/types/kanban.ts' => resource_path('js/types/kanban.ts'),
            ], 'laravel-kanban-frontend');
        }
    }

    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('kanban.route_prefix', 'kanban'),
            'middleware' => config('kanban.route_middleware', ['web', 'auth']),
            'as' => 'kanban.',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/kanban.php');
        });
    }
}
