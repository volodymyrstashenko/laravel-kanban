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

    /** Table name the package's own migrations constrain their user FKs against. */
    public static function usersTable(): string
    {
        return config('kanban.users_table') ?: 'users';
    }

    /**
     * Current tenant id from `config('kanban.institution_resolver')`, or null when the host
     * hasn't configured one (single-tenant — every KanbanBoard query then runs unscoped) or the
     * resolver itself returns null (e.g. a console command with no request/route to read).
     */
    public static function currentInstitutionId(): ?int
    {
        $resolver = config('kanban.institution_resolver');

        return $resolver ? $resolver() : null;
    }

    /**
     * Query for the "available users" list handed to the board index/show pages (member-add,
     * assignee pickers) — `config('kanban.available_users_resolver')` when the host set one,
     * else every user in `user_model` (userQuery()).
     */
    public static function availableUsersQuery()
    {
        $resolver = config('kanban.available_users_resolver');

        return $resolver ? $resolver() : static::userQuery();
    }

    /** Resolve the Inertia page name for one of the package's pages ("Index" | "Show"). */
    public static function page(string $name): string
    {
        return config('kanban.inertia_page_prefix', 'kanban/').$name;
    }
}
