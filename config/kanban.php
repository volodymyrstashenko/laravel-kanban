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
     | Routing
     |--------------------------------------------------------------------------
     | `route_prefix`     — URL prefix for every Kanban route (route names are
     |                      always `kanban.*`, unaffected by this).
     | `route_middleware` — middleware group the routes are wrapped in. Needs
     |                      `web` (session/CSRF/Inertia) plus the host's auth stack.
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
