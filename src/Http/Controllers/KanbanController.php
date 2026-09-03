<?php

namespace Thevps\Kanban\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Thevps\Kanban\Events\CardAssigned;
use Thevps\Kanban\Kanban;
use Thevps\Kanban\Models\KanbanActivity;
use Thevps\Kanban\Models\KanbanBoard;
use Thevps\Kanban\Models\KanbanBoardMember;
use Thevps\Kanban\Models\KanbanCard;
use Thevps\Kanban\Models\KanbanCardChecklist;
use Thevps\Kanban\Models\KanbanCardLink;
use Thevps\Kanban\Models\KanbanColumn;
use Thevps\Kanban\Models\KanbanComment;
use Thevps\Kanban\Support\LinkTitleFetcher;

/**
 * Повноцінна багатодошкова канбан-дошка. Уся app-специфіка відв'язана: модель користувача через
 * config('kanban.user_model'), сповіщення через подію CardAssigned (хост слухає й вирішує
 * канал), сторінки Inertia через config('kanban.inertia_page_prefix').
 *
 * Уся inline-авторизація (authorizeBoardAction/authorizeCardAction/isBoardOwner + IDOR-гарди
 * abort_unless($card->column->kanban_board_id === $board->id)) покрита тестами пакета
 * (tests/KanbanFlowTest.php).
 */
class KanbanController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $boards = KanbanBoard::query()
            ->where(function ($query) use ($userId) {
                $query->where('created_by_id', $userId)
                    ->orWhereExists(fn ($sub) => $sub
                        ->from('kanban_board_members')
                        ->whereColumn('kanban_board_id', 'kanban_boards.id')
                        ->where('user_id', $userId));
            })
            ->with('creator:id,name')
            ->orderBy('sort_order')
            ->get()
            ->map(function (KanbanBoard $board) {
                $board->cover_url = $board->coverUrl();

                return $board;
            });

        return Inertia::render(Kanban::page('Index'), [
            'boards' => $boards,
            'availableUsers' => Kanban::availableUsersQuery()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function reorderBoards(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:kanban_boards,id'],
        ]);

        KanbanBoard::setNewOrder($validated['ids']);

        return back();
    }

    public function storeBoard(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $request) {
            $board = KanbanBoard::create([
                ...$validated,
                'created_by_id' => $request->user()->id,
                'institution_id' => Kanban::currentInstitutionId(),
            ]);

            KanbanBoardMember::create([
                'kanban_board_id' => $board->id,
                'user_id' => $request->user()->id,
                'role' => 'owner',
            ]);

            KanbanBoard::createDefaultColumns($board);
        });

        return back()->with('success', 'Дошку створено.');
    }

    public function show(Request $request, KanbanBoard $board): Response
    {
        $member = $board->members()->where('user_id', $request->user()->id)->first();
        $isOwner = $this->isBoardOwner($board, $request->user()->id, $member);
        abort_unless($member || $board->created_by_id === $request->user()->id, 403);

        $columns = KanbanColumn::where('kanban_board_id', $board->id)
            ->orderBy('order_column')
            ->get();

        // Один запит на ВСІ картки дошки замість вкладеного with(['cards' => ...]) —
        // Laravel виконав би той eager-load ОКРЕМО на кожну колонку (N запитів на N колонок),
        // бо замикання прив'язане до relation, яка резолвиться по одній моделі-батьку за раз.
        // groupBy()->get(column_id) розкладає результат назад по колонках вручну — фінальний
        // вигляд даних (columns[].cards) для фронтенду ідентичний.
        $cardsByColumn = KanbanCard::query()
            ->whereIn('column_id', $columns->pluck('id'))
            ->active()
            ->with([
                'creator:id,name,email',
                'assignee:id,name,email',
                'media',
                'comments.user:id,name,email',
                // take(50) — журнал дій картки без ліміту міг би роздутись до тисяч рядків на
                // довгоживучих дошках; лише тут, у eager-load виклику, а не в самій relation-
                // функції на моделі, щоб не зачепити інші (наразі відсутні) місця, де activities
                // навмисно вантажаться без ліміту.
                'activities' => fn ($q) => $q->latest()->take(50)->with('user:id,name'),
                'checklists',
                'links',
                // Підзавдання можуть бути прилінковані з ІНШОЇ дошки (кросс-бордовий лінк,
                // linkSubtask()) — тож ключ і батька, і підзавдання рахується по ЙОГО ВЛАСНІЙ
                // дошці (column.board), а не по $board, яку зараз переглядають.
                'parent' => fn ($q) => $q->select(['id', 'column_id', 'number', 'title', 'archived_at'])
                    ->with(['column' => fn ($q2) => $q2->select(['id', 'kanban_board_id', 'title'])
                        ->with('board:id,title,code')]),
                // Легка вибірка колонок — не тягнемо весь опис/файли підзавдання в
                // прев'ю-список батьківської картки, лише те, що показує рядок у таб
                // «Підзавдання» (CardDetailsModal.vue).
                'subtasks' => fn ($q) => $q->select([
                    'id', 'parent_id', 'column_id', 'number', 'title', 'priority', 'assigned_to_id', 'archived_at',
                ])->with(['assignee:id,name,email', 'column:id,kanban_board_id,title,is_done', 'column.board:id,title,code']),
            ])
            ->withCount([
                'comments',
                'subtasks',
                'subtasks as subtasks_done_count' => fn ($q) => $q->whereNotNull('archived_at'),
            ])
            ->ordered()
            ->get()
            ->groupBy('column_id');

        foreach ($columns as $column) {
            // ->values() — groupBy() лишає оригінальні (непослідовні) ключі елементів із
            // повної колекції, а Laravel серіалізує колекцію в JSON-масив лише коли ключі
            // послідовні від 0; інакше Inertia віддала б columns[].cards як JS-об'єкт, не масив.
            $column->setRelation('cards', $cardsByColumn->get($column->id, collect())->values());
        }

        // Ключ картки (ADM-0001) — код дошки + номер картки, нуль-доповнений до 4 знаків.
        // Рахуємо тут, а не accessor-ом на моделі, бо для нього все одно потрібен $board (без
        // зайвого $card->column->board і N+1 при цьому) — АЛЕ для батька/підзавдань це має
        // бути ЇХНЯ ВЛАСНА дошка (може відрізнятись від $board при кросс-бордовому лінку).
        foreach ($columns as $column) {
            foreach ($column->cards as $card) {
                $card->display_key = $this->cardKey($board, $card->number);

                if ($card->parent) {
                    $parentBoard = $card->parent->column->board;
                    $card->parent_key = $this->cardKey($parentBoard, $card->parent->number);
                    $card->parent_board_title = $parentBoard->id !== $board->id ? $parentBoard->title : null;
                    $card->parent_board_id = $parentBoard->id !== $board->id ? $parentBoard->id : null;
                } else {
                    $card->parent_key = null;
                    $card->parent_board_title = null;
                    $card->parent_board_id = null;
                }

                foreach ($card->subtasks as $subtask) {
                    $subtaskBoard = $subtask->column->board;
                    $subtask->display_key = $this->cardKey($subtaskBoard, $subtask->number);
                    $subtask->board_title = $subtaskBoard->id !== $board->id ? $subtaskBoard->title : null;
                    $subtask->board_id = $subtaskBoard->id !== $board->id ? $subtaskBoard->id : null;
                }
            }
        }

        $board->load('creator:id,name,email');

        return Inertia::render(Kanban::page('Show'), [
            'board' => [
                ...$board->toArray(),
                'cover_url' => $board->coverUrl(),
            ],
            'columns' => $columns,
            'isOwner' => $isOwner,
            'members' => $board->members()->with('user:id,name,email')->get(),
            'availableUsers' => Kanban::availableUsersQuery()->orderBy('name')->get(['id', 'name', 'email']),
            'availableBoards' => KanbanBoard::where(function ($query) use ($request) {
                $query->where('created_by_id', $request->user()->id)
                    ->orWhereHas('members', fn ($q) => $q->where('user_id', $request->user()->id));
            })->get(['id', 'title', 'color']),
        ]);
    }

    public function update(Request $request, KanbanBoard $board): RedirectResponse
    {
        $this->authorizeBoardAction($request, $board, 'edit_board');
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:20'],
            // Тільки латинські букви/цифри — це префікс ключа картки (ADM-0001). Унікальність
            // без урахування регістру: код все одно приводиться до верхнього регістру в
            // мутаторі моделі.
            'code' => ['nullable', 'string', 'max:10', 'regex:/^[a-zA-Z0-9]+$/', Rule::unique('kanban_boards', 'code')->ignore($board->id)],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $board->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? null,
            'code' => $validated['code'] ?? null,
        ]);

        if ($request->hasFile('image')) {
            $board->clearMediaCollection('cover');
            $board->addMediaFromRequest('image')->toMediaCollection('cover');
        }

        return back()->with('success', 'Дошку збережено.');
    }

    public function destroy(Request $request, KanbanBoard $board): RedirectResponse
    {
        $this->authorizeBoardAction($request, $board, 'delete_board');
        $board->delete();

        return redirect()->route('kanban.index')->with('success', 'Дошку видалено.');
    }

    public function addMember(Request $request, KanbanBoard $board): RedirectResponse
    {
        $this->authorizeBoardAction($request, $board, 'manage_members');
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'in:editor,owner'],
        ]);

        if ($board->members()->where('user_id', $validated['user_id'])->exists()) {
            return back()->with('error', 'Цей користувач вже є учасником дошки.');
        }

        $board->members()->create($validated);

        return back()->with('success', 'Учасника додано.');
    }

    public function removeMember(Request $request, KanbanBoard $board, int $user): RedirectResponse
    {
        $this->authorizeBoardAction($request, $board, 'manage_members');
        $board->members()->where('user_id', $user)->delete();

        return back()->with('success', 'Учасника видалено.');
    }

    public function updateMemberRole(Request $request, KanbanBoard $board, int $user): RedirectResponse
    {
        $this->authorizeBoardAction($request, $board, 'manage_members');
        $validated = $request->validate(['role' => ['required', 'in:editor,owner']]);
        $board->members()->where('user_id', $user)->update($validated);

        return back();
    }

    public function storeColumn(Request $request, KanbanBoard $board): RedirectResponse
    {
        $this->authorizeBoardAction($request, $board, 'manage_columns');
        $validated = $request->validate(['title' => ['required', 'string', 'max:255']]);

        KanbanColumn::create([...$validated, 'kanban_board_id' => $board->id]);

        return back();
    }

    public function updateColumn(Request $request, KanbanBoard $board, KanbanColumn $column): RedirectResponse
    {
        $this->authorizeBoardAction($request, $board, 'manage_columns');
        abort_unless($column->kanban_board_id === $board->id, 404);
        $column->update($request->validate(['title' => ['required', 'string', 'max:255']]));

        return back();
    }

    public function destroyColumn(Request $request, KanbanBoard $board, KanbanColumn $column): RedirectResponse
    {
        $this->authorizeBoardAction($request, $board, 'manage_columns');
        abort_unless($column->kanban_board_id === $board->id, 404);
        $column->delete();

        return back()->with('success', 'Колонку видалено.');
    }

    public function reorderColumns(Request $request, KanbanBoard $board): RedirectResponse
    {
        $this->authorizeBoardAction($request, $board, 'manage_columns');
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:kanban_columns,id'],
        ]);

        KanbanColumn::setNewOrder($validated['ids']);

        return back();
    }

    public function storeCard(Request $request, KanbanBoard $board): RedirectResponse
    {
        $this->authorizeBoardAction($request, $board, 'manage_columns');
        $validated = $request->validate([
            'column_id' => ['required', 'exists:kanban_columns,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'assigned_to_id' => ['nullable', 'exists:users,id'],
            'color' => ['nullable', 'string', 'max:20'],
            'priority' => ['nullable', Rule::in([KanbanCard::PRIORITY_LOW, KanbanCard::PRIORITY_HIGH, KanbanCard::PRIORITY_ASAP])],
            // Підзавдання — та ж картка, лише з parent_id. Задається один раз, при створенні —
            // updateCard() його не приймає. Мусить лежати на цій самій дошці.
            'parent_id' => ['nullable', 'integer', function ($attribute, $value, $fail) use ($board) {
                if (! $value) {
                    return;
                }
                $parent = KanbanCard::with('column')->find($value);
                if (! $parent || $parent->column->kanban_board_id !== $board->id) {
                    $fail('Батьківська картка має належати цій самій дошці.');
                }
            }],
        ]);

        $card = KanbanCard::create([
            ...$validated,
            'created_by_id' => $request->user()->id,
            'number' => $board->nextCardNumber(),
        ]);
        $this->logActivity($card, $request, 'created', 'створив картку');

        if (! empty($validated['parent_id'])) {
            $parent = KanbanCard::find($validated['parent_id']);
            $this->logActivity($parent, $request, 'subtask_added', "додав підзавдання «{$card->title}»");
        }

        if ($card->assigned_to_id && $card->assigned_to_id !== $request->user()->id) {
            $card->loadMissing('column.board');
            event(new CardAssigned($card, $request->user()));
        }

        return back();
    }

    public function updateCard(Request $request, KanbanBoard $board, KanbanCard $card): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'edit');
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'assigned_to_id' => ['nullable', 'exists:users,id'],
            'color' => ['nullable', 'string', 'max:20'],
            'priority' => ['nullable', Rule::in([KanbanCard::PRIORITY_LOW, KanbanCard::PRIORITY_HIGH, KanbanCard::PRIORITY_ASAP])],
        ]);
        $card->update($validated);

        if ($card->wasChanged('title')) {
            $this->logActivity($card, $request, 'updated', "змінив назву картки на «{$card->title}»");
        }
        if ($card->wasChanged('description')) {
            $this->logActivity($card, $request, 'updated', 'оновив опис картки');
        }
        if ($card->wasChanged('due_date')) {
            $date = $card->due_date ? $card->due_date->format('d.m.Y') : 'видалено';
            $this->logActivity($card, $request, 'updated', "змінив дату виконання на {$date}");
        }
        if ($card->wasChanged('assigned_to_id') && $card->assigned_to_id) {
            $assignee = Kanban::userQuery()->find($card->assigned_to_id);
            $assigneeName = $assignee?->name ?? 'нікого';
            $this->logActivity($card, $request, 'updated', "призначив відповідальним {$assigneeName}");
            if ($card->assigned_to_id !== $request->user()->id) {
                $card->loadMissing('column.board');
                event(new CardAssigned($card, $request->user()));
            }
        }
        if ($card->wasChanged('color')) {
            $this->logActivity($card, $request, 'updated', 'змінив колір картки');
        }
        if ($card->wasChanged('priority')) {
            $this->logActivity($card, $request, 'updated', "змінив пріоритет на «{$this->priorityLabel($card->priority)}»");
        }

        return back();
    }

    /**
     * Прилінкувати ІСНУЮЧУ картку як підзавдання — на відміну від storeCard() (створює НОВУ
     * картку), тут просто переставляємо parent_id вже існуючій. Свідомо дозволяємо картку з
     * ІНШОЇ дошки — searchLinkableCards() нижче відсіює дошки без доступу.
     */
    public function linkSubtask(Request $request, KanbanBoard $board, KanbanCard $card): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'edit');

        $validated = $request->validate([
            'subtask_id' => ['required', 'integer', 'exists:kanban_cards,id', function ($attribute, $value, $fail) use ($request, $card) {
                if ((int) $value === $card->id) {
                    $fail('Картка не може бути підзавданням самої себе.');

                    return;
                }

                $subtask = KanbanCard::with('column.board')->find($value);
                if (! $subtask) {
                    return; // вже відхилено правилом exists вище
                }

                $targetBoard = $subtask->column->board;
                $userId = $request->user()->id;
                $hasAccess = $targetBoard->created_by_id === $userId || $targetBoard->members()->where('user_id', $userId)->exists();
                if (! $hasAccess) {
                    $fail('Ви не маєте доступу до дошки цієї картки.');

                    return;
                }

                if ($subtask->parent_id !== null) {
                    $fail('Ця картка вже є підзавданням іншої картки.');

                    return;
                }

                if ($subtask->subtasks()->exists()) {
                    $fail('У цієї картки вже є власні підзавдання — її не можна прилінкувати як підзавдання.');

                    return;
                }

                // Цикл: чи $card вже (прямо чи через кількох батьків) підзавдання картки $value?
                $ancestor = $card->parent;
                while ($ancestor) {
                    if ($ancestor->id === $subtask->id) {
                        $fail('Це створило б циклічне посилання між картками.');

                        return;
                    }
                    $ancestor = $ancestor->parent;
                }
            }],
        ]);

        $subtask = KanbanCard::with('column.board')->find($validated['subtask_id']);
        $subtask->update(['parent_id' => $card->id]);

        $targetBoard = $subtask->column->board;
        $note = $targetBoard->id !== $board->id ? " (з дошки «{$targetBoard->title}»)" : '';
        $this->logActivity($card, $request, 'subtask_added', "прилінкував підзавдання «{$subtask->title}»{$note}");

        return back()->with('success', 'Картку прилінковано як підзавдання.');
    }

    /** Відв'язати підзавдання від батьківської картки — сама картка-підзавдання НЕ видаляється. */
    public function unlinkSubtask(Request $request, KanbanBoard $board, KanbanCard $card, KanbanCard $subtask): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'edit');
        abort_unless($subtask->parent_id === $card->id, 404);

        $subtask->update(['parent_id' => null]);
        $this->logActivity($card, $request, 'subtask_removed', "відв'язав підзавдання «{$subtask->title}»");

        return back()->with('success', 'Підзавдання відв\'язано.');
    }

    /**
     * JSON-пошук карток, які МОЖНА прилінкувати як підзавдання до $card — з будь-якої дошки,
     * до якої в поточного користувача є доступ. Виключає: саму картку, картки, що вже є чиїмось
     * підзавданням, картки з власними підзавданнями, і весь ланцюжок предків $card.
     */
    public function searchLinkableCards(Request $request, KanbanBoard $board, KanbanCard $card)
    {
        $this->authorizeCardAction($request, $board, $card, 'edit');
        $search = trim((string) $request->query('q', ''));

        $accessibleBoardIds = KanbanBoard::where(function ($query) use ($request) {
            $query->where('created_by_id', $request->user()->id)
                ->orWhereHas('members', fn ($q) => $q->where('user_id', $request->user()->id));
        })->pluck('id');

        $ancestorIds = [];
        $ancestor = $card->parent;
        while ($ancestor) {
            $ancestorIds[] = $ancestor->id;
            $ancestor = $ancestor->parent;
        }

        $candidates = KanbanCard::query()
            ->active()
            ->whereNull('parent_id')
            ->whereDoesntHave('subtasks')
            ->where('id', '!=', $card->id)
            ->when($ancestorIds !== [], fn ($query) => $query->whereNotIn('id', $ancestorIds))
            ->whereHas('column', fn ($q) => $q->whereIn('kanban_board_id', $accessibleBoardIds))
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->with('column.board:id,title,code')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (KanbanCard $candidate) use ($board) {
                $candidateBoard = $candidate->column->board;

                return [
                    'id' => $candidate->id,
                    'title' => $candidate->title,
                    'display_key' => $this->cardKey($candidateBoard, $candidate->number),
                    'column_title' => $candidate->column->title,
                    'board_title' => $candidateBoard->id !== $board->id ? $candidateBoard->title : null,
                ];
            });

        return response()->json(['cards' => $candidates]);
    }

    public function moveCard(Request $request, KanbanBoard $board, KanbanCard $card): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'edit');
        $validated = $request->validate([
            // exists: сама колонка десь існує — цього не досить, її ще треба звірити з $board.
            'column_id' => ['required', 'exists:kanban_columns,id', function ($attribute, $value, $fail) use ($board) {
                if (! KanbanColumn::where('id', $value)->where('kanban_board_id', $board->id)->exists()) {
                    $fail('Колонка має належати цій самій дошці.');
                }
            }],
            'order' => ['required', 'array'],
        ]);

        $oldColumnTitle = $card->column->title;
        $card->update(['column_id' => $validated['column_id']]);
        $newColumnTitle = KanbanColumn::find($validated['column_id'])->title;

        if ($oldColumnTitle !== $newColumnTitle) {
            $this->logActivity($card, $request, 'moved', "перемістив з «{$oldColumnTitle}» у «{$newColumnTitle}»");
        }

        KanbanCard::setNewOrder($validated['order']);

        return back();
    }

    public function reorderCards(Request $request, KanbanBoard $board, KanbanColumn $column): RedirectResponse
    {
        $this->authorizeBoardAction($request, $board, 'manage_columns');
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:kanban_cards,id'],
        ]);

        KanbanCard::setNewOrder($validated['ids']);

        return back();
    }

    public function destroyCard(Request $request, KanbanBoard $board, KanbanCard $card): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'delete');
        $card->delete();

        return back()->with('success', 'Картку видалено.');
    }

    public function archiveCard(Request $request, KanbanBoard $board, KanbanCard $card): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'archive');
        $card->update(['archived_at' => now()]);
        $this->logActivity($card, $request, 'archived', 'архівував картку');

        return back()->with('success', 'Картку архівовано.');
    }

    public function restoreCard(Request $request, KanbanBoard $board, KanbanCard $card): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'archive');
        $card->update(['archived_at' => null]);
        $this->logActivity($card, $request, 'restored', 'відновив картку з архіву');

        return back()->with('success', 'Картку відновлено.');
    }

    public function assignToMe(Request $request, KanbanBoard $board, KanbanCard $card): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'edit');
        $card->update(['assigned_to_id' => $request->user()->id]);
        $this->logActivity($card, $request, 'updated', 'призначив себе відповідальним');

        return back()->with('success', 'Ви тепер відповідальні за цю картку.');
    }

    public function storeChecklistItem(Request $request, KanbanBoard $board, KanbanCard $card): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'checklist');
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'titles' => ['nullable', 'array'],
            'titles.*' => ['string', 'max:255'],
        ]);

        if (! empty($validated['titles'])) {
            $count = 0;
            foreach ($validated['titles'] as $title) {
                if (trim($title) !== '') {
                    $card->checklists()->create(['title' => $title]);
                    $count++;
                }
            }
            if ($count > 0) {
                $this->logActivity($card, $request, 'checklist_added', "додав {$count} пунктів чек-листа");
            }
        } elseif (! empty($validated['title'])) {
            $card->checklists()->create(['title' => $validated['title']]);
            $this->logActivity($card, $request, 'checklist_added', "додав пункт чек-листа: {$validated['title']}");
        }

        return back();
    }

    public function updateChecklistItem(Request $request, KanbanBoard $board, KanbanCard $card, KanbanCardChecklist $item): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'checklist');
        abort_unless($item->card_id === $card->id, 404);
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);
        $oldTitle = $item->title;
        $item->update($validated);

        if ($request->has('is_completed')) {
            $status = $item->is_completed ? 'виконав' : 'відмітив як невиконаний';
            $this->logActivity($card, $request, 'checklist_updated', "{$status} пункт: {$item->title}");
        } elseif ($request->has('title') && $item->title !== $oldTitle) {
            $this->logActivity($card, $request, 'checklist_updated', "змінив пункт чек-листа: «{$oldTitle}» → «{$item->title}»");
        }

        return back();
    }

    public function destroyChecklistItem(Request $request, KanbanBoard $board, KanbanCard $card, KanbanCardChecklist $item): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'checklist');
        abort_unless($item->card_id === $card->id, 404);
        $title = $item->title;
        $item->delete();
        $this->logActivity($card, $request, 'checklist_deleted', "видалив пункт чек-листа: {$title}");

        return back();
    }

    public function storeComment(Request $request, KanbanBoard $board, KanbanCard $card): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'comment');
        $validated = $request->validate(['content' => ['required', 'string']]);

        KanbanComment::create([
            'card_id' => $card->id,
            'user_id' => $request->user()->id,
            'content' => $validated['content'],
        ]);
        $this->logActivity($card, $request, 'commented', 'залишив коментар');

        return back();
    }

    public function destroyComment(Request $request, KanbanBoard $board, KanbanComment $comment): RedirectResponse
    {
        // Той самий IDOR, що й у authorizeCardAction(): {comment} треба звірити з {board}
        // з маршруту, інакше isBoardOwner($board, ...) рахує права від СВОЄЇ дошки виклика.
        abort_unless($comment->card->column->kanban_board_id === $board->id, 404);

        $userId = $request->user()->id;
        abort_unless($comment->user_id === $userId || $this->isBoardOwner($board, $userId), 403);
        $comment->delete();

        return back()->with('success', 'Коментар видалено.');
    }

    public function storeAttachment(Request $request, KanbanBoard $board, KanbanCard $card): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'edit');
        $validated = $request->validate([
            'files' => ['required', 'array'],
            // Без mime-обмеження spatie/laravel-medialibrary блокує лише виконувані розширення
            // (.php/.jsp) — .html/.svg проходили б і зберігались на ТОМУ Ж домені (прямий лінк,
            // target="_blank") → stored XSS. Дозволений список — офісні документи/архіви/зображення.
            'files.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,7z,jpg,jpeg,png,gif,webp'],
        ]);

        foreach ($validated['files'] as $file) {
            $card->addMedia($file)
                ->withCustomProperties(['user_name' => $request->user()->name])
                ->toMediaCollection('attachments');
        }
        $this->logActivity($card, $request, 'attached', 'додав файли');

        return back()->with('success', 'Файли додано.');
    }

    public function destroyAttachment(Request $request, KanbanBoard $board, KanbanCard $card, int $mediaId): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'edit');
        $media = $card->media()->findOrFail($mediaId);
        $name = $media->file_name;
        $media->delete();
        $this->logActivity($card, $request, 'detached', "видалив файл: {$name}");

        return back()->with('success', 'Файл видалено.');
    }

    public function storeLink(Request $request, KanbanBoard $board, KanbanCard $card): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'edit');
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048', 'url', 'starts_with:http://,https://'],
        ]);

        // Синхронно, best-effort: заголовок цільової сторінки. null → фронтенд покаже сам URL.
        $title = LinkTitleFetcher::fetch($validated['url']);

        $card->links()->create([
            'url' => $validated['url'],
            'title' => $title,
            'created_by_id' => $request->user()->id,
        ]);
        $this->logActivity($card, $request, 'link_added', 'додав посилання: '.($title ?: $validated['url']));

        return back()->with('success', 'Посилання додано.');
    }

    public function destroyLink(Request $request, KanbanBoard $board, KanbanCard $card, KanbanCardLink $link): RedirectResponse
    {
        $this->authorizeCardAction($request, $board, $card, 'edit');
        abort_unless($link->card_id === $card->id, 404);

        $label = $link->title ?: $link->url;
        $link->delete();
        $this->logActivity($card, $request, 'link_removed', "видалив посилання: {$label}");

        return back()->with('success', 'Посилання видалено.');
    }

    private function logActivity(KanbanCard $card, Request $request, string $type, string $description): void
    {
        KanbanActivity::create([
            'card_id' => $card->id,
            'user_id' => $request->user()->id,
            'type' => $type,
            'description' => $description,
        ]);
    }

    /** "ADM-0001" — null, якщо в дошки нема коду або в картки ще нема номера. */
    private function cardKey(KanbanBoard $board, ?int $number): ?string
    {
        return ($board->code && $number) ? sprintf('%s-%04d', $board->code, $number) : null;
    }

    /** Той самий текст, що й PRIORITY_OPTIONS у resources/js/lib/priority.ts — лише для журналу дій. */
    private function priorityLabel(?string $priority): string
    {
        return match ($priority) {
            KanbanCard::PRIORITY_LOW => 'Низька',
            KanbanCard::PRIORITY_HIGH => 'Висока',
            KanbanCard::PRIORITY_ASAP => 'Терміново',
            default => 'без пріоритету',
        };
    }

    private function authorizeBoardAction(Request $request, KanbanBoard $board, string $action): void
    {
        $userId = $request->user()->id;
        $isCreator = $board->created_by_id === $userId;
        $member = $board->members()->where('user_id', $userId)->first();
        $isOwner = $this->isBoardOwner($board, $userId, $member);

        match ($action) {
            'manage_members', 'delete_board', 'edit_board' => abort_unless($isOwner, 403, 'Лише власник дошки може виконати цю дію.'),
            'manage_columns' => abort_unless($member || $isCreator, 403, 'Ви не є учасником цієї дошки.'),
            default => null,
        };
    }

    private function authorizeCardAction(Request $request, KanbanBoard $board, KanbanCard $card, string $action): void
    {
        // Захист від IDOR: {board} і {card} у маршруті — незалежні route-model-binding'и, тож
        // без цієї перевірки хтось, хто є owner/creator ХОЧ ЯКОЇСЬ своєї дошки, міг би
        // редагувати/архівувати/видаляти картку будь-якої ЧУЖОЇ дошки, підставивши її id в URL.
        abort_unless($card->column->kanban_board_id === $board->id, 404);

        $userId = $request->user()->id;
        $isBoardCreator = $board->created_by_id === $userId;
        $member = $board->members()->where('user_id', $userId)->first();
        $isBoardOwner = $this->isBoardOwner($board, $userId, $member);
        $isAssignee = $card->assigned_to_id === $userId;

        abort_unless($member || $isBoardCreator, 403);

        if (in_array($action, ['archive', 'delete'], true)) {
            abort_unless($isBoardOwner || $isAssignee, 403, 'Ви не власник дошки і не виконавець цієї картки.');
        }
    }

    /**
     * Єдиний критерій "власник дошки": буквальний творець (created_by_id) АБО член дошки з
     * роллю 'owner' у kanban_board_members (власник додається туди й сам, див. storeBoard()).
     */
    private function isBoardOwner(KanbanBoard $board, int $userId, ?KanbanBoardMember $member = null): bool
    {
        $member ??= $board->members()->where('user_id', $userId)->first();

        return $board->created_by_id === $userId || ($member && $member->role === 'owner');
    }
}
