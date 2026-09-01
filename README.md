# thevps/laravel-kanban

Багатодошкова канбан-дошка для Laravel + Inertia + Vue: дошки з учасниками (owner/editor),
колонки, картки (опис, виконавець, дедлайн, колір, важливість, чек-лист, коментарі, вкладення,
журнал дій), підзавдання (в т.ч. кросбордові), ключі карток у стилі Jira (`ADM-0001`),
drag-and-drop через `vuedraggable`, порядок через `spatie/eloquent-sortable`.

Винесено з `app-strogaz.devvps.pp.ua`. App-специфіка відв'язана:

| Що | Як |
|---|---|
| Модель користувача | `config('kanban.user_model')` |
| Сповіщення виконавцю | подія `Thevps\Kanban\Events\CardAssigned` — хост слухає й сам шле Telegram/пошту/нічого |
| Сторінки Inertia | `config('kanban.inertia_page_prefix')` (типово `kanban/`) |
| Маршрути | `config('kanban.route_prefix')` + `config('kanban.route_middleware')`, імена завжди `kanban.*` |

## Встановлення

```bash
composer require thevps/laravel-kanban
php artisan vendor:publish --tag=laravel-kanban-config
php artisan vendor:publish --tag=laravel-kanban-frontend
php artisan migrate
```

`vendor:publish --tag=laravel-kanban-frontend` копіює Vue/TS у:

```
resources/js/components/kanban/*.vue
resources/js/pages/kanban/{Index,Show}.vue
resources/js/lib/{kanbanColors,priority}.ts
resources/js/types/kanban.ts
```

Далі відредагуйте `config/kanban.php` (`user_model`, `route_middleware`).

## Точки адаптації фронтенду (хост володіє копіями)

Опубліковані компоненти імпортують з хост-застосунку — переконайтеся, що є або підмініть:

- **UI-кіт** `@/components/ui/*` (shadcn-vue): `alert-dialog avatar badge button dialog
  dropdown-menu input label select separator tabs textarea tooltip`. Для проєктів на **reka-ui**
  (а не radix-vue) — імпорти самих примітивів усередині цих компонентів однакові, але переконайтеся,
  що ваш `ui/*` теж на reka-ui.
- `@/layouts/AppLayout.vue` — макет сторінки (breadcrumbs slot). `pages/kanban/{Index,Show}.vue`.
- `@/components/ConfirmDeleteDialog.vue` — контрольований діалог підтвердження (`open` + `@confirm`).
- `@/composables/useInitials` — ініціали для аватарів.
- Тости через Inertia flash (`success`/`error`) — контролер лише робить `->with('success', ...)`.
- npm: `vuedraggable`, `lucide-vue-next`, `@inertiajs/vue3`.
- Tailwind-токени: `--success`/`--success-foreground`, `--warning`/`--warning-foreground`,
  `--sidebar-border` (світла+темна тема).

## Подія `CardAssigned`

Кидається при появі виконавця (створення картки чи зміна `assigned_to_id`), **крім**
самопризначення. `$card` завантажена з `column.board`.

```php
use Illuminate\Support\Facades\Event;
use Thevps\Kanban\Events\CardAssigned;

Event::listen(CardAssigned::class, function (CardAssigned $e) {
    $e->card->assignee?->notify(new CardAssignedNotification($e->card, $e->assignedBy));
});
```

## Розробка / локальний path-репозиторій

```json
"repositories": [
    { "type": "path", "url": "packages/laravel-kanban", "options": { "symlink": true } }
]
```
```bash
composer require thevps/laravel-kanban:@dev
```
