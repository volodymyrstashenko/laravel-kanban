<?php

return [
    /*
     |--------------------------------------------------------------------------
     | User model
     |--------------------------------------------------------------------------
     | The Eloquent model used for board members, card creators/assignees,
     | comment authors and activity actors. Must expose `id`, `name` and
     | (optionally) `email`.
     */
    'user_model' => \App\Models\User::class,

    /*
     |--------------------------------------------------------------------------
     | Routing
     |--------------------------------------------------------------------------
     | `prefix`     — URL prefix for every Kanban route (route names are always
     |                `kanban.*`, unaffected by this).
     | `middleware` — middleware group the routes are wrapped in. Needs `web`
     |                (session/CSRF/Inertia) and whatever auth stack the host app
     |                uses.
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
];
