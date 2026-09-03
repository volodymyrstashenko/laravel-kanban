<script setup lang="ts">
import BoardSettingsModal from '@/components/kanban/BoardSettingsModal.vue';
import KanbanBoardComponent from '@/components/kanban/KanbanBoard.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/AppLayout.vue';
import { getContrastTextColor } from '@/lib/kanbanColors';
import type { BreadcrumbItem, KanbanBoard, KanbanColumn, KanbanUserRef } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronDown, LayoutTemplate, Plus, Settings, User as UserIcon } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    board: KanbanBoard;
    columns: KanbanColumn[];
    isOwner: boolean;
    members: { id: number; user_id: number; role: 'owner' | 'editor'; user: KanbanUserRef }[];
    availableUsers: KanbanUserRef[];
    availableBoards: { id: number; title: string; color: string | null }[];
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Канбан', href: route('kanban.index') },
    { title: props.board.title, href: route('kanban.show', props.board.id) },
]);

const isSettingsOpen = ref(false);
const showOnlyMine = ref(false);

const headerTextColor = computed(() => (props.board.color ? getContrastTextColor(props.board.color) : null));
const headerStyle = computed(() => (props.board.color ? { backgroundColor: props.board.color } : {}));
const headerTitleStyle = computed(() => (headerTextColor.value ? { color: headerTextColor.value } : {}));
const iconStyle = computed(() => {
    if (!headerTextColor.value) return {};
    const onWhite = headerTextColor.value === '#ffffff';
    return { backgroundColor: onWhite ? 'rgba(255,255,255,0.18)' : 'rgba(17,24,39,0.1)', color: headerTextColor.value };
});
</script>

<template>
    <Head :title="board.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col overflow-hidden">
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
                :class="board.color ? '' : ''"
                :style="headerStyle"
            >
                <div class="group relative flex items-center gap-3">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground" :style="iconStyle">
                        <LayoutTemplate class="size-4" />
                    </div>
                    <div class="cursor-pointer">
                        <h1 class="flex items-center gap-1.5 text-base font-semibold leading-none text-foreground" :style="headerTitleStyle">
                            {{ board.title }}
                            <ChevronDown
                                class="size-4 text-muted-foreground"
                                :style="headerTextColor ? { color: headerTextColor, opacity: 0.6 } : {}"
                            />
                        </h1>
                    </div>

                    <div
                        v-if="availableBoards.length > 1"
                        class="invisible absolute left-0 top-9 z-30 w-64 rounded-lg border border-sidebar-border/70 bg-card p-1.5 opacity-0 shadow-lg transition-all group-hover:visible group-hover:opacity-100 dark:border-sidebar-border"
                    >
                        <Link
                            v-for="b in availableBoards"
                            :key="b.id"
                            :href="route('kanban.show', b.id)"
                            class="flex items-center gap-2.5 rounded-md p-2 text-sm hover:bg-muted"
                            :class="b.id === board.id ? 'bg-primary/10 text-primary' : 'text-foreground'"
                        >
                            <div
                                class="flex size-7 items-center justify-center rounded-md bg-primary/10 text-primary"
                                :style="b.color ? { backgroundColor: `color-mix(in srgb, ${b.color} 25%, transparent)`, color: b.color } : {}"
                            >
                                <LayoutTemplate class="size-3.5" />
                            </div>
                            <span class="truncate font-medium">{{ b.title }}</span>
                        </Link>
                        <Separator class="my-1.5" />
                        <Link
                            :href="route('kanban.index')"
                            class="flex items-center gap-2.5 rounded-md p-2 text-sm text-muted-foreground hover:bg-muted"
                        >
                            <Plus class="size-3.5" />
                            Всі дошки
                        </Link>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Button
                        variant="secondary"
                        size="sm"
                        class="gap-1.5"
                        :class="showOnlyMine ? 'bg-primary text-primary-foreground hover:bg-primary/90' : ''"
                        @click="showOnlyMine = !showOnlyMine"
                    >
                        <UserIcon class="size-4" />
                        Мої завдання
                    </Button>
                    <Button variant="secondary" size="sm" class="gap-1.5" @click="isSettingsOpen = true">
                        <Settings class="size-4" />
                        Налаштування
                    </Button>
                </div>
            </div>

            <KanbanBoardComponent
                class="flex-1"
                :columns="columns"
                :available-users="availableUsers"
                :board="board"
                :is-owner="isOwner"
                :members="members"
                :show-only-mine="showOnlyMine"
            />
        </div>

        <BoardSettingsModal v-if="isOwner" v-model:open="isSettingsOpen" :board="board" :members="members" :available-users="availableUsers" />
    </AppLayout>
</template>
