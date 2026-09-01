<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { KanbanBoard, KanbanCard, KanbanColumn as KanbanColumnType, KanbanUserRef } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import CardDetailsModal from './CardDetailsModal.vue';
import KanbanColumn from './KanbanColumn.vue';

const props = defineProps<{
    columns: KanbanColumnType[];
    availableUsers: KanbanUserRef[];
    board: KanbanBoard;
    isOwner: boolean;
    members: { user_id: number; user: KanbanUserRef }[];
    showOnlyMine: boolean;
}>();

const page = usePage();
const currentUserId = computed(() => (page.props.auth as { user: { id: number } }).user.id);

const boardColumns = ref([...props.columns]);

watch(
    () => props.columns,
    (columns) => {
        boardColumns.value = [...columns];

        if (selectedCard.value) {
            for (const column of columns) {
                const found = column.cards.find((c) => c.id === selectedCard.value?.id);
                if (found) {
                    selectedCard.value = found;
                    break;
                }
            }
        }
    },
    { deep: true },
);

const selectedCard = ref<KanbanCard | null>(null);
const isModalOpen = ref(false);

function handleOpenCard(card: KanbanCard) {
    selectedCard.value = card;
    isModalOpen.value = true;
}

/**
 * Перехід між картками з модалки (підзавдання ⇄ батьківська картка) — картка вже лежить у
 * boardColumns (кожне підзавдання — звичайна картка у своїй колонці), тож просто шукаємо її
 * там і підміняємо selectedCard, не закриваючи модалку. Якщо не знайшли (картка архівована —
 * active()-скоуп на бекенді ховає такі з columns[].cards) — нічого не робимо: CardDetailsModal.vue
 * рендерить архівовані підзавдання/батьків як нативні (некликабельні), тож сюди в такому
 * випадку взагалі не повинні потрапляти.
 */
function handleNavigate(cardId: number) {
    for (const column of boardColumns.value) {
        const found = column.cards.find((c) => c.id === cardId);
        if (found) {
            selectedCard.value = found;
            return;
        }
    }
}

const defaultColumnId = computed(() => boardColumns.value[0]?.id ?? null);

function handleReorderColumns() {
    const ids = boardColumns.value.map((c) => c.id);
    router.post(route('kanban.columns.reorder', props.board.id), { ids }, { preserveScroll: true });
}

function handleReorderCards({ columnId, event }: { columnId: number; event: any }) {
    const column = boardColumns.value.find((c) => c.id === columnId);
    if (!column) return;

    if (event.added) {
        const cardId = event.added.element.id;
        const order = column.cards.map((c) => c.id);
        router.post(route('kanban.cards.move', [props.board.id, cardId]), { column_id: columnId, order }, { preserveScroll: true });
    } else if (event.moved) {
        const order = column.cards.map((c) => c.id);
        router.post(route('kanban.columns.reorder-cards', [props.board.id, columnId]), { ids: order }, { preserveScroll: true });
    }
}

const isCreateColumnOpen = ref(false);
const newColumnTitle = ref('');

function createColumn() {
    if (!newColumnTitle.value.trim()) return;
    router.post(
        route('kanban.columns.store', props.board.id),
        { title: newColumnTitle.value },
        {
            onSuccess: () => {
                isCreateColumnOpen.value = false;
                newColumnTitle.value = '';
            },
        },
    );
}

const isCreateCardOpen = ref(false);
const newCardTitle = ref('');
const activeColumnId = ref<number | null>(null);

function handleAddCard(columnId: number) {
    activeColumnId.value = columnId;
    newCardTitle.value = '';
    isCreateCardOpen.value = true;
}

function createCard() {
    if (!newCardTitle.value.trim() || !activeColumnId.value) return;
    router.post(
        route('kanban.cards.store', props.board.id),
        { column_id: activeColumnId.value, title: newCardTitle.value },
        {
            onSuccess: () => {
                isCreateCardOpen.value = false;
                newCardTitle.value = '';
            },
        },
    );
}

function handleUpdateColumn({ id, title }: { id: number; title: string }) {
    router.put(route('kanban.columns.update', [props.board.id, id]), { title }, { preserveScroll: true });
}

const columnToDeleteId = ref<number | null>(null);

function confirmDeleteColumn() {
    if (!columnToDeleteId.value) return;
    router.delete(route('kanban.columns.destroy', [props.board.id, columnToDeleteId.value]), {
        onSuccess: () => {
            columnToDeleteId.value = null;
        },
    });
}
</script>

<template>
    <div class="kanban-scroll flex-1 overflow-x-auto overflow-y-hidden p-4">
        <draggable
            v-model="boardColumns"
            group="columns"
            item-key="id"
            handle=".column-handle"
            class="flex h-full gap-4 pb-4"
            ghost-class="opacity-50"
            @change="handleReorderColumns"
        >
            <template #item="{ element }">
                <KanbanColumn
                    :column="element"
                    :show-only-mine="showOnlyMine"
                    :current-user-id="currentUserId"
                    @add-card="handleAddCard"
                    @reorder-cards="handleReorderCards"
                    @update-column="handleUpdateColumn"
                    @delete-column="columnToDeleteId = $event"
                    @open-card="handleOpenCard"
                />
            </template>

            <template #footer>
                <div class="w-72 shrink-0">
                    <button
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border py-4 text-sm font-semibold text-muted-foreground transition-all hover:border-primary/50 hover:bg-card hover:text-primary"
                        @click="isCreateColumnOpen = true"
                    >
                        <Plus class="size-4" />
                        Нова колонка
                    </button>
                </div>
            </template>
        </draggable>

        <CardDetailsModal
            :card="selectedCard"
            v-model:open="isModalOpen"
            :available-users="availableUsers"
            :members="members"
            :board="board"
            :is-owner="isOwner"
            :default-column-id="defaultColumnId"
            @navigate="handleNavigate"
        />

        <Dialog v-model:open="isCreateColumnOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Створити нову колонку</DialogTitle>
                    <DialogDescription class="sr-only">Введіть назву для нової колонки на дошці.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <Input v-model="newColumnTitle" placeholder="Назва колонки" autofocus @keyup.enter="createColumn" />
                </div>
                <DialogFooter>
                    <Button variant="secondary" @click="isCreateColumnOpen = false">Скасувати</Button>
                    <Button @click="createColumn">Створити</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="isCreateCardOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Додати нову картку</DialogTitle>
                    <DialogDescription class="sr-only">Введіть назву для нового завдання.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <Input v-model="newCardTitle" placeholder="Назва завдання" autofocus @keyup.enter="createCard" />
                </div>
                <DialogFooter>
                    <Button variant="secondary" @click="isCreateCardOpen = false">Скасувати</Button>
                    <Button @click="createCard">Додати</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AlertDialog :open="columnToDeleteId !== null">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Ви впевнені?</AlertDialogTitle>
                    <AlertDialogDescription>Ця дія незворотна. Це назавжди видалить колонку та всі картки в ній.</AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="columnToDeleteId = null">Скасувати</AlertDialogCancel>
                    <AlertDialogAction @click="confirmDeleteColumn">Видалити</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>

<style>
.kanban-scroll::-webkit-scrollbar {
    height: 8px;
}
.kanban-scroll::-webkit-scrollbar-track {
    background: transparent;
}
.kanban-scroll::-webkit-scrollbar-thumb {
    background: hsl(var(--muted-foreground) / 0.2);
    border-radius: 10px;
}
.dark .kanban-scroll::-webkit-scrollbar-thumb {
    background: hsl(var(--muted-foreground) / 0.1);
}
</style>
