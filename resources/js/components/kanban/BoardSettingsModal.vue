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
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useInitials } from '@/composables/useInitials';
import { KANBAN_COLORS } from '@/lib/kanbanColors';
import type { KanbanBoard, KanbanUserRef } from '@/types';
import { router, useForm } from '@inertiajs/vue3';
import { Check, Loader2, MoreVertical, ShieldAlert, Trash2, Upload, UserPlus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

interface Props {
    open: boolean;
    board: KanbanBoard;
    members: { id: number; user_id: number; role: 'owner' | 'editor'; user: KanbanUserRef }[];
    availableUsers: KanbanUserRef[];
}

const props = defineProps<Props>();
const emit = defineEmits<{ 'update:open': [value: boolean] }>();
const { getInitials } = useInitials();

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const form = useForm({
    title: '',
    description: '',
    code: '',
    image: null as File | null,
    color: null as string | null,
});

const imagePreview = ref<string | null>(null);

function handleImageUpload(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    form.image = file;
    const reader = new FileReader();
    reader.onload = (e) => (imagePreview.value = e.target?.result as string);
    reader.readAsDataURL(file);
}

function updateBoard() {
    // .put() (не .post()) — Inertia сама спуфить це в POST+_method лише коли форма містить File,
    // інакше шле справжній PUT; маршрут kanban.update зареєстрований саме як PUT.
    form.put(route('kanban.update', props.board.id), { preserveScroll: true });
}

function deleteBoard() {
    router.delete(route('kanban.destroy', props.board.id));
}

const memberForm = useForm({ user_id: '', role: 'editor' });

function addMember() {
    memberForm.post(route('kanban.members.store', props.board.id), {
        preserveScroll: true,
        onSuccess: () => memberForm.reset(),
    });
}

function removeMember(userId: number) {
    router.delete(route('kanban.members.destroy', [props.board.id, userId]), { preserveScroll: true });
}

function updateRole(userId: number, role: string) {
    router.put(route('kanban.members.update', [props.board.id, userId]), { role }, { preserveScroll: true });
}

watch(
    () => props.board,
    (board) => {
        if (!board) return;
        form.title = board.title;
        form.description = board.description ?? '';
        form.code = board.code ?? '';
        form.color = board.color ?? null;
        imagePreview.value = board.cover_url ?? null;
    },
    { immediate: true },
);

const uninvitedUsers = computed(() => {
    const memberIds = props.members.map((m) => m.user_id);
    return props.availableUsers.filter((u) => !memberIds.includes(u.id) && u.id !== props.board.created_by_id);
});

// Дошка автоматично додає створювача як члена з роллю owner (KanbanController::storeBoard) —
// не показувати його ще раз у списку нижче, він уже є окремим пінованим рядком «Власник».
const otherMembers = computed(() => props.members.filter((m) => m.user_id !== props.board.created_by_id));
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-w-2xl p-0">
            <div class="border-b border-sidebar-border/70 px-6 py-4 dark:border-sidebar-border">
                <DialogHeader>
                    <DialogTitle>Налаштування дошки</DialogTitle>
                    <DialogDescription>Керування параметрами дошки та учасниками.</DialogDescription>
                </DialogHeader>
            </div>

            <div class="p-6">
                <Tabs default-value="general" class="w-full">
                    <TabsList class="mb-6 grid w-full grid-cols-2">
                        <TabsTrigger value="general">Загальні</TabsTrigger>
                        <TabsTrigger value="members">Учасники</TabsTrigger>
                    </TabsList>

                    <TabsContent value="general" class="space-y-6 outline-none">
                        <div class="grid gap-2">
                            <Label for="board-title">Назва дошки</Label>
                            <Input id="board-title" v-model="form.title" placeholder="Мій проєкт" />
                            <span v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</span>
                        </div>

                        <div class="grid gap-2">
                            <Label for="board-description">Опис</Label>
                            <Textarea id="board-description" v-model="form.description" placeholder="Короткий опис проєкту…" class="min-h-[90px]" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="board-code">Код дошки</Label>
                            <Input
                                id="board-code"
                                v-model="form.code"
                                placeholder="ADM"
                                maxlength="10"
                                class="w-32 font-mono uppercase"
                                @input="form.code = form.code.toUpperCase()"
                            />
                            <span v-if="form.errors.code" class="text-xs text-destructive">{{ form.errors.code }}</span>
                            <p class="text-xs text-muted-foreground">
                                Короткий префікс для номера картки, наприклад <span class="font-mono">ADM</span> → картки матимуть ключі
                                <span class="font-mono">ADM-0001</span>, <span class="font-mono">ADM-0002</span>… Без коду картки показують просто
                                <span class="font-mono">#id</span>.
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label>Колір дошки</Label>
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="color in KANBAN_COLORS"
                                    :key="color"
                                    type="button"
                                    class="flex size-7 items-center justify-center rounded-full ring-1 ring-black/10 transition-transform hover:scale-110"
                                    :style="{ backgroundColor: color }"
                                    @click="form.color = color"
                                >
                                    <Check v-if="form.color === color" class="size-3.5 text-white" />
                                </button>
                                <button
                                    type="button"
                                    class="flex size-7 items-center justify-center rounded-full border-2 border-dashed border-muted-foreground/40 text-muted-foreground hover:border-destructive hover:text-destructive"
                                    @click="form.color = null"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                            <p class="text-xs text-muted-foreground">Забарвлює шапку дошки — зручно для швидкого пошуку серед кількох дошок.</p>
                        </div>

                        <div class="grid gap-2">
                            <Label>Зображення обкладинки</Label>
                            <div class="flex items-center gap-4 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                                <div
                                    class="flex h-20 w-32 shrink-0 items-center justify-center overflow-hidden rounded-md border border-dashed border-border bg-card"
                                >
                                    <img v-if="imagePreview" :src="imagePreview" class="h-full w-full object-cover" alt="" />
                                    <Upload v-else class="size-5 text-muted-foreground" />
                                </div>
                                <div class="relative flex-1">
                                    <Input
                                        type="file"
                                        accept="image/*"
                                        class="absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0"
                                        @change="handleImageUpload"
                                    />
                                    <Button variant="outline" size="sm" class="w-full gap-1.5"><Upload class="size-3.5" /> Вибрати файл</Button>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t border-sidebar-border/70 pt-6 dark:border-sidebar-border">
                            <AlertDialog>
                                <AlertDialogTrigger as-child>
                                    <Button variant="ghost" size="sm" class="text-destructive hover:bg-destructive/10 hover:text-destructive">
                                        <Trash2 class="mr-1.5 size-4" /> Видалити дошку
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>Ви впевнені?</AlertDialogTitle>
                                        <AlertDialogDescription
                                            >Ця дія незворотна. Це назавжди видалить дошку та всі її картки, коментарі й
                                            файли.</AlertDialogDescription
                                        >
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>Скасувати</AlertDialogCancel>
                                        <AlertDialogAction @click="deleteBoard">Видалити назавжди</AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>

                            <Button :disabled="form.processing" @click="updateBoard">
                                <Loader2 v-if="form.processing" class="mr-1.5 size-4 animate-spin" />
                                Зберегти зміни
                            </Button>
                        </div>
                    </TabsContent>

                    <TabsContent value="members" class="space-y-6 outline-none">
                        <div class="flex items-end gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                            <div class="flex-1 space-y-1.5">
                                <Label class="text-[11px] uppercase text-muted-foreground">Додати учасника</Label>
                                <Select v-model="memberForm.user_id">
                                    <SelectTrigger><SelectValue placeholder="Оберіть користувача" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="u in uninvitedUsers" :key="u.id" :value="String(u.id)">{{ u.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="w-32 space-y-1.5">
                                <Label class="text-[11px] uppercase text-muted-foreground">Роль</Label>
                                <Select v-model="memberForm.role">
                                    <SelectTrigger><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="editor">Редактор</SelectItem>
                                        <SelectItem value="owner">Власник</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button :disabled="!memberForm.user_id || memberForm.processing" @click="addMember">
                                <UserPlus class="size-4" />
                            </Button>
                        </div>

                        <div class="max-h-[350px] space-y-2 overflow-y-auto">
                            <div class="flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 p-3">
                                <div class="flex items-center gap-3">
                                    <Avatar size="sm" class="size-9"
                                        ><AvatarFallback class="bg-primary/10 text-primary">{{
                                            getInitials(board.creator?.name ?? '')
                                        }}</AvatarFallback></Avatar
                                    >
                                    <div>
                                        <p class="text-sm font-semibold text-foreground">{{ board.creator?.name }}</p>
                                        <p class="text-[10px] uppercase tracking-wide text-muted-foreground">Власник (створив дошку)</p>
                                    </div>
                                </div>
                                <Badge class="gap-1"><ShieldAlert class="size-3" /> Власник</Badge>
                            </div>

                            <div
                                v-for="member in otherMembers"
                                :key="member.id"
                                class="group flex items-center justify-between rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                            >
                                <div class="flex items-center gap-3">
                                    <Avatar size="sm" class="size-9"
                                        ><AvatarFallback class="bg-muted text-muted-foreground">{{
                                            getInitials(member.user.name)
                                        }}</AvatarFallback></Avatar
                                    >
                                    <div>
                                        <p class="text-sm font-semibold text-foreground">{{ member.user.name }}</p>
                                        <p class="text-[11px] text-muted-foreground">{{ member.user.email }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Badge variant="outline">{{ member.role === 'owner' ? 'Власник' : 'Редактор' }}</Badge>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="size-8 opacity-0 group-hover:opacity-100">
                                                <MoreVertical class="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>Дії</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem @click="updateRole(member.user_id, 'editor')">Зробити редактором</DropdownMenuItem>
                                            <DropdownMenuItem @click="updateRole(member.user_id, 'owner')">Зробити власником</DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem class="text-destructive" @click="removeMember(member.user_id)">
                                                <Trash2 class="mr-2 size-4" /> Видалити учасника
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </div>

                            <div v-if="otherMembers.length === 0" class="flex flex-col items-center justify-center py-8 text-muted-foreground">
                                <UserPlus class="mb-2 size-8 opacity-20" />
                                <p class="text-xs">Немає інших учасників</p>
                            </div>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </DialogContent>
    </Dialog>
</template>
