# thevps/laravel-kanban

Багатодошкова канбан-дошка для Laravel + Inertia + Vue: дошки з учасниками (owner/editor),
колонки, картки (опис, виконавець, дедлайн, колір, важливість, чек-лист, коментарі, вкладення,
журнал дій), підзавдання (в т.ч. кросбордові), ключі карток у стилі Jira (`ADM-0001`),
drag-and-drop через `vuedraggable`, порядок через `spatie/eloquent-sortable`.

Пакет не прив'язаний до конкретного застосунку — усе, що зазвичай різниться між проєктами,
винесено в конфіг чи подію:

| Що | Як |
|---|---|
| Модель користувача | `config('kanban.user_model')` |
| Таблиця користувачів (для FK у міграціях) | `config('kanban.users_table')` (типово `users`) |
| Сповіщення виконавцю | подія `Thevps\Kanban\Events\CardAssigned` — хост слухає й сам шле Telegram/пошту/нічого |
| Сторінки Inertia | `config('kanban.inertia_page_prefix')` (типово `kanban/`) |
| Маршрути | `config('kanban.route_prefix')` + `config('kanban.route_middleware')`, імена завжди `kanban.*` |
| Мультитенантність | `config('kanban.institution_resolver')` — див. нижче |
| Список доступних користувачів | `config('kanban.available_users_resolver')` — див. нижче |

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

- **UI-кіт** `@/components/ui/*` (shadcn-vue на **reka-ui**): `alert-dialog avatar badge button
  dialog dropdown-menu input label select separator tabs textarea tooltip`.
- `@/layouts/AppLayout.vue` — макет сторінки (breadcrumbs slot). `pages/kanban/{Index,Show}.vue`.
- `@/components/ConfirmDeleteDialog.vue` — контрольований діалог підтвердження (`open` + `@confirm`).
- `@/composables/useInitials` — ініціали для аватарів.
- Тости через Inertia flash (`success`/`error`) — контролер лише робить `->with('success', ...)`.
- npm: `vuedraggable`, `@lucide/vue`, `@inertiajs/vue3`.
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

## Мультитенантність (опційно)

Пакет за замовчуванням однотенантний — `kanban_boards.institution_id` присутня в схемі завжди
(на випадок майбутньої потреби), але нею ніхто не користується, доки хост не задасть резолвер:

```php
// config/kanban.php
'institution_resolver' => fn () => request()->route('institution')?->id,
```

Тоді `KanbanBoard` автоматично фільтрує **кожен** запит (глобальний scope) по
`institution_id`, а `storeBoard()` проставляє його для нових дошок. Щоб дошки взагалі жили під
`{institution:slug}/kanban/...`, а не глобально на `/kanban/...`, `route_prefix` приймає
route-параметри як звичайний рядок префікса:

```php
'route_prefix' => '{institution:slug}/kanban',
'route_middleware' => ['web', 'auth', 'verified', /* хостовий middleware, що резолвить {institution} */],
```

Список `availableUsers` (пікери учасника/виконавця) за замовчуванням — усі користувачі
`user_model`; якщо це неприйнятно для мультитенантного хоста (юзери кількох закладів в одній
таблиці), підмінити:

```php
'available_users_resolver' => fn () => \App\Models\User::query()
    ->whereHas('institutions', fn ($q) => $q->where('id', request()->route('institution')->id)),
```

Аналогічно — `group_id`/`member_sync` на `kanban_boards` і `document_task_id` на `kanban_cards`:
порожні колонки без FK, які пакет ніколи сам не читає й не пише — місце для хоста зберігати
власний зв'язок (дошка групи/команди, картка від зовнішньої задачі) без форку моделей.

## Тести

```bash
composer install
vendor/bin/phpunit
```

Пакетний Testbench-сьют (`tests/KanbanFlowTest.php`) не залежить від жодного хост-застосунку —
піднімає власного тестового користувача й БД у пам'яті.

## Розробка / локальний path-репозиторій

Для розробки пакета всередині якогось проєкту, без публікації в реєстр:

```json
"repositories": [
    { "type": "path", "url": "packages/laravel-kanban", "options": { "symlink": true } }
]
```
```bash
composer require thevps/laravel-kanban:@dev
```
