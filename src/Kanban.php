<?php

namespace Thevps\Kanban;

use Illuminate\Database\Eloquent\Model;

class Kanban
{
    /**
     * FQCN of the host application's user model, from `config('kanban.user_model')`.
     *
     * The fallback is a string literal on purpose — the package must never `use` or hard-
     * reference `App\Models\User` (it is not guaranteed to exist, and that coupling is exactly
     * what this package removes). Hosts always publish config/kanban.php with a real class.
     */
    public static function userModel(): string
    {
        return config('kanban.user_model') ?: 'App\Models\User';
    }

    /** A fresh query builder on the host user model. */
    public static function userQuery()
    {
        /** @var class-string<Model> $class */
        $class = static::userModel();

        return $class::query();
    }

    /** Resolve the Inertia page name for one of the package's pages ("Index" | "Show"). */
    public static function page(string $name): string
    {
        return config('kanban.inertia_page_prefix', 'kanban/').$name;
    }
}
