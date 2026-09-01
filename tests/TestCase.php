<?php

namespace Thevps\Kanban\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Inertia\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\EloquentSortable\EloquentSortableServiceProvider;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;
use Thevps\Kanban\KanbanServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            EloquentSortableServiceProvider::class,
            MediaLibraryServiceProvider::class,
            ServiceProvider::class,
            KanbanServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('kanban.user_model', TestUser::class);
        $app['config']->set('kanban.route_middleware', ['web']);
        // Виводимо Inertia як JSON у тестах, не шукаючи реальні .vue-файли.
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}

class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = true;
}
