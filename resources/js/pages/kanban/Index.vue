<script setup lang="ts">
import BoardSettingsModal from '@/components/kanban/BoardSettingsModal.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { getContrastTextColor } from '@/lib/kanbanColors';
import type { BreadcrumbItem, KanbanBoard, KanbanUserRef } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Clock, GripVertical, LayoutTemplate, Plus, Settings, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';

const props = defineProps<{
    boards: KanbanBoard[];
    availableUsers: KanbanUserRef[];
}>();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Канбан', href: route('kanban.index') }];

const localBoards = ref([...props.boards]);
watch(
    () => props.boards,
    (boards) => (localBoards.value = [...boards]),
    { deep: true },
);

const isCreateOpen = ref(false);
const form = useForm({ title: '', description: '' });

function submit() {
    form.post(route('kanban.store'), {
        onSuccess: () => {
            isCreateOpen.value = false;
            form.reset();
        },
    });
}

function handleReorder() {
    const ids = localBoards.value.map((b) => b.id);
    router.post(route('kanban.reorder'), { ids }, { preserveScroll: true });
}

const isSettingsOpen = ref(false);
const selectedBoard = ref<KanbanBoard | null>(null);

function openSettings(board: KanbanBoard) {
    selectedBoard.value = board;
    isSettingsOpen.value = true;
}
</script>

<template>
    <Head title="Канбан" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-semibold">Ваші дошки</h1>
                    <p class="text-sm text-muted-foreground">Виберіть дошку для роботи із завданнями або створіть нову.</p>
                </div>
                <Button size="sm" class="gap-1.5" @click="isCreateOpen = true">
                    <Plus class="size-4" />
                    Нова дошка
                </Button>
            </div>

            <draggable
                v-model="localBoards"
                item-key="id"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                ghost-class="opacity-50"
                handle=".drag-handle"
                @change="handleReorder"
            >
                <template #item="{ element: board }">
                    <div
                        class="group relative flex flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-card transition-all hover:-translate-y-0.5 hover:shadow-md dark:border-sidebar-border"
                    >
                        <div
                            class="drag-handle absolute right-2 top-2 z-10 cursor-grab rounded-md p-1 text-muted-foreground opacity-0 hover:bg-muted/50 hover:text-foreground active:cursor-grabbing group-hover:opacity-100"
                        >
                            <GripVertical class="size-4" />
                        </div>
                        <button
                            type="button"
                            class="absolute right-9 top-2 z-10 rounded-md p-1 text-muted-foreground opacity-0 hover:bg-muted/50 hover:text-foreground group-hover:opacity-100"
                            title="Налаштування"
                            @click.stop.prevent="openSettings(board)"
                        >
                            <Settings class="size-4" />
                        </button>

                        <Link :href="route('kanban.show', board.id)" class="flex flex-1 flex-col">
                            <div
                                v-if="board.cover_url"
                                class="h-28 w-full shrink-0 border-b border-sidebar-border/70 bg-cover bg-center dark:border-sidebar-border"
                                :style="{ backgroundImage: `url(${board.cover_url})` }"
                            />
                            <div v-else class="p-4 pb-0">
                                <div
                                    class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary transition-transform group-hover:scale-105"
                                    :style="
                                        board.color
                                            ? { backgroundColor: `color-mix(in srgb, ${board.color} 20%, var(--card))`, color: board.color }
                                            : {}
                                    "
                                >
                                    <LayoutTemplate class="size-5" />
                                </div>
                            </div>

                            <div class="flex flex-1 flex-col px-4 pt-3">
                                <h3 class="mb-1 line-clamp-1 text-base font-semibold text-foreground">{{ board.title }}</h3>
                                <p class="mb-4 line-clamp-2 h-9 text-xs text-muted-foreground">{{ board.description || 'Немає опису' }}</p>
                            </div>

                            <div
                                class="flex items-center justify-between px-4 py-2.5 text-[11px] font-medium"
                                :class="board.color ? '' : 'border-t border-sidebar-border/70 text-muted-foreground dark:border-sidebar-border'"
                                :style="board.color ? { backgroundColor: board.color, color: getContrastTextColor(board.color) } : {}"
                            >
                                <span class="flex items-center gap-1.5" :style="board.color ? { opacity: 0.85 } : {}">
                                    <Clock class="size-3.5" />
                                    {{ new Date(board.created_at).toLocaleDateString('uk-UA') }}
                                </span>
                                <span class="flex items-center gap-1.5" :style="board.color ? { opacity: 0.85 } : {}">
                                    <Users class="size-3.5" />
                                    {{ board.creator?.name ?? 'Автор' }}
                                </span>
                            </div>
                        </Link>
                    </div>
                </template>

                <template #footer>
                    <button
                        type="button"
                        class="flex min-h-[190px] flex-col items-center justify-center rounded-xl border-2 border-dashed border-border p-8 text-muted-foreground transition-all hover:border-primary/40 hover:text-primary"
                        @click="isCreateOpen = true"
                    >
                        <div class="mb-3 flex size-11 items-center justify-center rounded-full bg-muted group-hover:bg-primary/10">
                            <Plus class="size-6" />
                        </div>
                        <span class="text-sm font-medium">Нова дошка</span>
                    </button>
                </template>
            </draggable>
        </div>

        <Dialog v-model:open="isCreateOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Створити нову дошку</DialogTitle>
                    <DialogDescription>Заповніть форму нижче, щоб створити нову дошку завдань.</DialogDescription>
                </DialogHeader>
                <div class="grid gap-4 py-4">
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Назва</label>
                        <Input v-model="form.title" placeholder="Наприклад: Маркетинг" />
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Опис</label>
                        <Textarea v-model="form.description" placeholder="Короткий опис проєкту…" />
                    </div>
                </div>
                <DialogFooter>
                    <Button :disabled="form.processing" @click="submit">Створити</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <BoardSettingsModal
            v-if="selectedBoard"
            v-model:open="isSettingsOpen"
            :board="selectedBoard"
            :members="selectedBoard.members ?? []"
            :available-users="availableUsers"
        />
    </AppLayout>
</template>
