<?php

return [
    /*
     |--------------------------------------------------------------------------
     | User model
     |--------------------------------------------------------------------------
     | The Eloquent model used for board members, card creators/assignees,
     | comment authors and activity actors. Must expose `id`, `name` and
     | (optionally) `email`. Set this to your app's user model after publishing.
     */
    'user_model' => 'App\Models\User',

    /*
     |--------------------------------------------------------------------------
     | Users table
     |--------------------------------------------------------------------------
     | Table name the package's own migrations `->constrained()` against for
     | created_by_id/assigned_to_id/user_id columns. Most hosts use Laravel's
     | default `users`; set this before running `php artisan migrate` if yours
     | is named differently (e.g. a prefixed `core_users`).
     */
    'users_table' => 'users',

    /*
     |--------------------------------------------------------------------------
     | Routing
     |--------------------------------------------------------------------------
     | `route_prefix`     — URL prefix for every Kanban route (route names are
     |                      always `kanban.*`, unaffected by this). Accepts
     |                      route-parameter placeholders, e.g.
     |                      '{institution:slug}/kanban' for a multi-tenant host —
     |                      Laravel resolves the bound model normally, the
     |                      controller just never references it directly.
     | `route_middleware` — middleware group the routes are wrapped in. Needs
     |                      `web` (session/CSRF/Inertia) plus the host's auth stack,
     |                      plus whatever middleware resolves the route prefix's
     |                      bound model(s) (e.g. a tenant-context middleware).
     */
    'route_prefix' => 'kanban',
    'route_middleware' => ['web', 'auth'],

    /*
     |--------------------------------------------------------------------------
     | Inertia page prefix
     |--------------------------------------------------------------------------
     | Controller renders `{prefix}Index` and `{prefix}Show`. The published Vue
     | pages live at `resources/js/pages/kanban/{Index,Show}.vue` by default.
     */
    'inertia_page_prefix' => 'kanban/',

    /*
     |--------------------------------------------------------------------------
     | Multi-tenancy (optional)
     |--------------------------------------------------------------------------
     | The package is single-tenant by default — leave `institution_resolver`
     | null and `kanban_boards.institution_id` (present on every install, see
     | the add_institution_id_to_kanban_boards_table migration) is simply
     | unused. A multi-tenant host sets this to a callable returning the
     | current tenant's id (or null when there isn't one, e.g. a console
     | command); KanbanBoard applies a global scope filtering on it whenever
     | the resolver returns non-null, and KanbanController::storeBoard()
     | stamps new boards with it. Nothing else in the package reads it —
     | column/card/comment/etc. queries are scoped through their board
     | relationship, not this column directly.
     |
     | Example: 'institution_resolver' => fn () => request()->route('institution')?->id,
     */
    'institution_resolver' => null,

    /*
     |--------------------------------------------------------------------------
     | Available users (optional)
     |--------------------------------------------------------------------------
     | Board index/show pass an `availableUsers` list (for the member-add and
     | assignee pickers) sourced from `Kanban::availableUsersQuery()`, which
     | defaults to `Kanban::userQuery()` (every user in `user_model`). A host
     | where users are scoped some other way (e.g. per-tenant role
     | assignments) can override just this list without touching board/card
     | authorship, which stays on the plain `user_model` relations.
     |
     | Signature: callable(): \Illuminate\Database\Eloquent\Builder
     */
    'available_users_resolver' => null,
];
