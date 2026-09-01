<?php

namespace Thevps\Kanban;

use Illuminate\Database\Eloquent\Model;

class Kanban
{
    /** FQCN of the host application's user model, from `config('kanban.user_model')`. */
    public static function userModel(): string
    {
        return config('kanban.user_model', \App\Models\User::class);
    }

    /** A fresh query builder on the host user model. */
    public static function userQuery()
    {
        /** @var Model $class */
        $class = static::userModel();

        return $class::query();
    }

    /** Resolve the Inertia page name for one of the package's pages ("Index" | "Show"). */
    public static function page(string $name): string
    {
        return config('kanban.inertia_page_prefix', 'kanban/').$name;
    }
}
