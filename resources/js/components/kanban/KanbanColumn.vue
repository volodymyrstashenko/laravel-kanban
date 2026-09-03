<script setup lang="ts">
import type { KanbanCard as KanbanCardType, KanbanColumn } from '@/types';
import { GripVertical, MoreVertical, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import draggable from 'vuedraggable';
import KanbanCard from './KanbanCard.vue';

const props = defineProps<{
    column: KanbanColumn;
    showOnlyMine: boolean;
    currentUserId: number;
}>();

const emit = defineEmits<{
    'add-card': [columnId: number];
    'reorder-cards': [payload: { columnId: number; event: any }];
    'update-column': [payload: { id: number; title: string }];
    'delete-column': [id: number];
    'open-card': [card: KanbanCardType];
}>();

const isEditing = ref(false);
const title = ref(props.column.title);
const menuOpen = ref(false);

const vFocus = {
    mounted: (el: HTMLElement) => el.focus(),
};

function saveTitle() {
    if (title.value.trim() && title.value !== props.column.title) {
        emit('update-column', { id: props.column.id, title: title.value });
    } else {
        title.value = props.column.title;
    }
    isEditing.value = false;
}

/**
 * Підзавдання (`parent_id`) — повноцінна картка й може лежати в будь-якій колонці незалежно
 * від батька (той самий сенс, що й досі — див. CLAUDE.md § Канбан, "підзавдання"). Тут лише
 * ВІЗУАЛЬНЕ групування: коли батько ТЕЖ у цій колонці — відступаємо картку-підзавдання
 * (KanbanCard.vue's `indent`), щоб вона читалась як підкартка головної (на прохання
 * користувача: "може показувати допкартки як підкартки головної"). Коли батько в іншій
 * колонці — батька тут не видно, нести підказки для читача, тож відступ не додаємо.
 */
function isNestedHere(card: KanbanCardType): boolean {
    return card.parent_id !== null && props.column.cards.some((c) => c.id === card.parent_id);
}
</script>

<template>
    <div class="flex h-full w-72 shrink-0 flex-col rounded-xl border border-sidebar-border/70 bg-muted/50 pb-2 dark:border-sidebar-border">
        <div class="group/head flex items-center justify-between gap-1 p-2.5 pb-2">
            <div class="flex min-w-0 flex-1 items-center gap-1.5">
                <GripVertical
                    class="column-handle size-4 shrink-0 cursor-grab text-muted-foreground/40 opacity-0 transition-opacity active:cursor-grabbing group-hover/head:opacity-100"
                />
                <input
                    v-if="isEditing"
                    v-model="title"
                    v-focus
                    type="text"
                    class="w-full rounded-md border border-input bg-background px-2 py-1 text-sm font-semibold shadow-sm outline-none focus:ring-2 focus:ring-primary"
                    @blur="saveTitle"
                    @keyup.enter="saveTitle"
                    @keyup.esc="((title = column.title), (isEditing = false))"
                />
                <div v-else class="flex min-w-0 items-center gap-2">
                    <h3 class="cursor-pointer truncate text-sm font-semibold text-foreground" @click="isEditing = true">{{ column.title }}</h3>
                    <span class="rounded-full bg-card px-1.5 py-0.5 text-[10px] font-semibold text-muted-foreground">{{ column.cards.length }}</span>
                </div>
            </div>

            <div class="flex items-center gap-0.5 opacity-0 transition-opacity group-hover/head:opacity-100">
                <button
                    type="button"
                    class="rounded-md p-1.5 text-muted-foreground hover:bg-card hover:text-primary"
                    @click="emit('add-card', column.id)"
                >
                    <Plus class="size-4" />
                </button>
                <div class="relative">
                    <button
                        type="button"
                        class="rounded-md p-1.5 text-muted-foreground hover:bg-card hover:text-foreground"
                        @click="menuOpen = !menuOpen"
                    >
                        <MoreVertical class="size-4" />
                    </button>
                    <div v-if="menuOpen" class="fixed inset-0 z-10" @click="menuOpen = false" />
                    <div
                        v-if="menuOpen"
                        class="absolute right-0 top-full z-20 mt-1 w-44 rounded-lg border border-sidebar-border/70 bg-card py-1 shadow-lg dark:border-sidebar-border"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium text-destructive hover:bg-destructive/10"
                            @click="((menuOpen = false), emit('delete-column', column.id))"
                        >
                            <Trash2 class="size-3.5" />
                            Видалити колонку
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- vuedraggable потребує СПРАВЖНЬОГО writable-масиву для live-анімації перетягування
             (сам компонент керує splice() під час драгу). `column` тут завжди елемент
             KanbanBoard.vue::boardColumns — локальний ref батька, не прямий prop дошки —
             мутація коректно поширюється вгору тим самим об'єктом; реальна персистенція
             порядку — окремим HTTP-запитом у @change нижче. -->
        <!-- eslint-disable vue/no-mutating-props -->
        <draggable
            v-model="column.cards"
            group="cards"
            item-key="id"
            class="min-h-[40px] flex-1 space-y-0 overflow-y-auto px-2.5"
            ghost-class="opacity-40"
            drag-class="rotate-1"
            @change="emit('reorder-cards', { columnId: column.id, event: $event })"
        >
            <!-- eslint-enable vue/no-mutating-props -->
            <template #item="{ element }">
                <KanbanCard
                    v-show="!showOnlyMine || element.assigned_to_id === currentUserId"
                    :card="element"
                    :indent="isNestedHere(element)"
                    @click="emit('open-card', element)"
                />
            </template>
        </draggable>

        <div class="px-2.5">
            <button
                type="button"
                class="flex w-full items-center gap-1.5 rounded-lg px-2.5 py-2 text-xs font-medium text-muted-foreground transition-colors hover:bg-primary/10 hover:text-primary"
                @click="emit('add-card', column.id)"
            >
                <Plus class="size-3.5" />
                Додати картку
            </button>
        </div>
    </div>
</template>
