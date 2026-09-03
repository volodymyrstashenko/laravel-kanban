<script setup lang="ts">
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useInitials } from '@/composables/useInitials';
import { KANBAN_COLORS } from '@/lib/kanbanColors';
import { PRIORITY_OPTIONS, priorityBadgeVariant, priorityLabel, type Priority } from '@/lib/priority';
import type { KanbanBoard, KanbanCard, KanbanMedia, KanbanSubtaskRef, KanbanUserRef } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    Download,
    Eye,
    File,
    FileArchive,
    FileAudio,
    FileCode,
    FileImage,
    FileSpreadsheet,
    FileText,
    FileVideo,
    ExternalLink,
    History,
    Link2,
    ListTree,
    MessageSquare,
    Paperclip,
    Pencil,
    Plus,
    Send,
    Square,
    Trash2,
    User,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    card: KanbanCard | null;
    open: boolean;
    availableUsers: KanbanUserRef[];
    members: { user_id: number; user: KanbanUserRef }[];
    board: KanbanBoard;
    isOwner: boolean;
    /** Колонка, куди падає нове підзавдання при швидкому створенні (перша колонка дошки). */
    defaultColumnId: number | null;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean]; navigate: [cardId: number] }>();
const page = usePage();
const { getInitials } = useInitials();

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const activeTab = ref('details');
const isEditing = ref(false);
const commentContent = ref('');

const form = useForm({
    title: '',
    description: '',
    assigned_to_id: null as number | null,
});

watch(
    () => props.card,
    (card, previousCard) => {
        if (card) {
            form.title = card.title;
            form.description = card.description ?? '';
            form.assigned_to_id = card.assigned_to_id;
        }
        // Тільки перехід на ІНШУ картку (підзавдання ⇄ батько, id змінився) скидає активну
        // вкладку/форму редагування. `card` — новий об'єкт-референс після КОЖНОГО Inertia-
        // релоаду (додав підзавдання, коментар, змінив чекліст…), навіть коли це та сама
        // картка — без цієї перевірки на id вкладку "Підзавдання" вибивало назад на "Деталі"
        // одразу після додавання підзавдання.
        if (card?.id !== previousCard?.id) {
            isEditing.value = false;
            activeTab.value = 'details';
        }
    },
    { immediate: true },
);

function openCard(id: number) {
    emit('navigate', id);
}

const newSubtaskTitle = ref('');
const isAddingSubtask = ref(false);

function addSubtask() {
    if (!props.card || !props.defaultColumnId || !newSubtaskTitle.value.trim()) return;
    isAddingSubtask.value = true;
    router.post(
        route('kanban.cards.store', props.board.id),
        { column_id: props.defaultColumnId, title: newSubtaskTitle.value, parent_id: props.card.id },
        { preserveScroll: true, onSuccess: () => (newSubtaskTitle.value = ''), onFinish: () => (isAddingSubtask.value = false) },
    );
}

function subtaskIsDone(subtask: KanbanSubtaskRef) {
    return subtask.archived_at !== null;
}

interface LinkableCard {
    id: number;
    title: string;
    display_key: string | null;
    column_title: string;
    board_title: string | null;
}

const isLinkingSubtask = ref(false);
const linkSearchQuery = ref('');
const linkResults = ref<LinkableCard[]>([]);
const isSearchingLink = ref(false);
const linkError = ref<string | null>(null);
let linkSearchTimeout: ReturnType<typeof setTimeout> | undefined;

async function searchLinkableCards(query: string) {
    if (!props.card) return;
    isSearchingLink.value = true;
    try {
        const response = await fetch(`${route('kanban.cards.linkable-cards', [props.board.id, props.card.id])}?q=${encodeURIComponent(query)}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        linkResults.value = data.cards ?? [];
    } catch {
        linkResults.value = [];
    } finally {
        isSearchingLink.value = false;
    }
}

function openLinkSubtask() {
    isLinkingSubtask.value = true;
    linkError.value = null;
    linkSearchQuery.value = '';
    searchLinkableCards('');
}

watch(linkSearchQuery, (query) => {
    if (linkSearchTimeout) clearTimeout(linkSearchTimeout);
    linkSearchTimeout = setTimeout(() => searchLinkableCards(query), 300);
});

function linkSubtask(candidate: LinkableCard) {
    if (!props.card) return;
    linkError.value = null;
    router.post(
        route('kanban.cards.link-subtask', [props.board.id, props.card.id]),
        { subtask_id: candidate.id },
        {
            preserveScroll: true,
            onSuccess: () => (isLinkingSubtask.value = false),
            onError: (errors) => (linkError.value = errors.subtask_id ?? 'Не вдалося прилінкувати картку.'),
        },
    );
}

const unlinkingSubtask = ref<KanbanSubtaskRef | null>(null);
const isUnlinkingSubtask = ref(false);

function unlinkSubtask(subtask: KanbanSubtaskRef) {
    unlinkingSubtask.value = subtask;
}

function confirmUnlinkSubtask() {
    if (!props.card || !unlinkingSubtask.value) return;
    isUnlinkingSubtask.value = true;
    router.delete(route('kanban.cards.subtasks.destroy', [props.board.id, props.card.id, unlinkingSubtask.value.id]), {
        preserveScroll: true,
        onFinish: () => {
            isUnlinkingSubtask.value = false;
            unlinkingSubtask.value = null;
        },
    });
}

function updateCard() {
    if (!props.card) return;
    form.put(route('kanban.cards.update', [props.board.id, props.card.id]), {
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
        },
    });
}

function postComment() {
    if (!props.card || !commentContent.value.trim()) return;
    router.post(
        route('kanban.cards.comments.store', [props.board.id, props.card.id]),
        { content: commentContent.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                commentContent.value = '';
            },
        },
    );
}

const formattedDueDate = computed(() => {
    if (!props.card?.due_date) return 'Без дедлайну';
    return new Date(props.card.due_date).toLocaleDateString('uk-UA', { day: 'numeric', month: 'long', year: 'numeric' });
});

const isOverdue = computed(() => {
    if (!props.card?.due_date) return false;
    return new Date(props.card.due_date) < new Date(new Date().toDateString());
});

const currentUser = computed(() => (page.props.auth as { user: { id: number; name: string } }).user);
const isAssignee = computed(() => props.card?.assigned_to_id === currentUser.value.id);
const canArchive = computed(() => props.isOwner || isAssignee.value);

function handleArchive() {
    if (!props.card) return;
    router.post(
        route('kanban.cards.archive', [props.board.id, props.card.id]),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                isOpen.value = false;
            },
        },
    );
}

// Дедлайн — миттєве збереження прямо в перегляді, без входу в "Редагувати" (той самий підхід,
// що колір/важливість нижче) — на прохання користувача: "встановлення дедлайну прям в
// перегляді а не через редагування". Шлемо лише {due_date}, не весь form — router.put() з
// частковим payload'ом уже перевірений на color/priority: KanbanController::updateCard()'s
// $request->validate() кладе в $validated лише ключі, що РЕАЛЬНО присутні в тілі запиту
// (Validator::validated() пропускає взагалі відсутні поля незалежно від 'nullable'), тож інші
// поля картки цим запитом не зачіпаються.
const isSettingDueDate = ref(false);
function setCardDueDate(dueDate: string | null) {
    if (!props.card || isSettingDueDate.value) return;
    const current = props.card.due_date ? props.card.due_date.slice(0, 10) : null;
    if (current === dueDate) return;
    isSettingDueDate.value = true;
    router.put(
        route('kanban.cards.update', [props.board.id, props.card.id]),
        { due_date: dueDate },
        { preserveScroll: true, onFinish: () => (isSettingDueDate.value = false) },
    );
}

const isSettingColor = ref(false);
function setCardColor(color: string | null) {
    if (!props.card || isSettingColor.value || props.card.color === color) return;
    isSettingColor.value = true;
    router.put(
        route('kanban.cards.update', [props.board.id, props.card.id]),
        { color },
        { preserveScroll: true, onFinish: () => (isSettingColor.value = false) },
    );
}

const isSettingPriority = ref(false);
function setCardPriority(priority: Priority | null) {
    if (!props.card || isSettingPriority.value || props.card.priority === priority) return;
    isSettingPriority.value = true;
    router.put(
        route('kanban.cards.update', [props.board.id, props.card.id]),
        { priority },
        { preserveScroll: true, onFinish: () => (isSettingPriority.value = false) },
    );
}

function handleAssignMe() {
    if (!props.card) return;
    router.post(route('kanban.cards.assign-me', [props.board.id, props.card.id]), {}, { preserveScroll: true });
}

const newChecklistItem = ref('');
function addChecklistItem() {
    if (!props.card) return;
    const lines = newChecklistItem.value
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean);
    if (lines.length === 0) return;
    router.post(
        route('kanban.cards.checklists.store', [props.board.id, props.card.id]),
        { titles: lines },
        { preserveScroll: true, onSuccess: () => (newChecklistItem.value = '') },
    );
}

function toggleChecklistItem(item: { id: number; is_completed: boolean }) {
    if (!props.card) return;
    router.put(
        route('kanban.cards.checklists.update', [props.board.id, props.card.id, item.id]),
        { is_completed: !item.is_completed },
        { preserveScroll: true },
    );
}

function deleteChecklistItem(item: { id: number }) {
    if (!props.card) return;
    router.delete(route('kanban.cards.checklists.destroy', [props.board.id, props.card.id, item.id]), { preserveScroll: true });
}

const editingChecklistId = ref<number | null>(null);
const editingChecklistTitle = ref('');

function startEditChecklistItem(item: { id: number; title: string }) {
    editingChecklistId.value = item.id;
    editingChecklistTitle.value = item.title;
}

function cancelEditChecklistItem() {
    editingChecklistId.value = null;
    editingChecklistTitle.value = '';
}

function saveChecklistItem(item: { id: number; title: string }) {
    if (!props.card) return;
    const title = editingChecklistTitle.value.trim();
    if (!title || title === item.title) return cancelEditChecklistItem();
    router.put(
        route('kanban.cards.checklists.update', [props.board.id, props.card.id, item.id]),
        { title },
        { preserveScroll: true, onSuccess: () => cancelEditChecklistItem() },
    );
}

const cardFileInput = ref<HTMLInputElement | null>(null);

function triggerCardFileInput() {
    cardFileInput.value?.click();
}

function handleCardFileChange(e: Event) {
    if (!props.card) return;
    const files = (e.target as HTMLInputElement).files;
    if (!files || files.length === 0) return;
    router.post(
        route('kanban.cards.attachments.store', [props.board.id, props.card.id]),
        { files: Array.from(files) },
        { forceFormData: true, preserveScroll: true },
    );
}

const deletingMediaId = ref<number | null>(null);
const deletingAttachment = ref(false);

const previewingMedia = ref<KanbanMedia | null>(null);
const previewText = ref<string | null>(null);
const previewTextLoading = ref(false);
const PREVIEW_TEXT_LIMIT = 300_000; // chars — enough for any real note/log, keeps a huge file from choking the tab

/** Formats the browser can render inline via a plain <img>/<iframe>/<video>/<audio> tag (or, for
 *  plain text, a fetched-and-rendered <pre>) — everything else (Office docs, archives, etc.) only
 *  gets a download link, there's no universal in-browser viewer for them. */
function previewKind(mimeType: string): 'image' | 'pdf' | 'video' | 'audio' | 'text' | null {
    if (!mimeType) return null;
    if (mimeType.startsWith('image/')) return 'image';
    if (mimeType === 'application/pdf') return 'pdf';
    if (mimeType.startsWith('video/')) return 'video';
    if (mimeType.startsWith('audio/')) return 'audio';
    if (mimeType.startsWith('text/')) return 'text';
    return null;
}

// Text files are fetched and rendered into a <pre> ourselves rather than pointed at with an
// <iframe src> — Spatie MediaLibrary commonly serves attachments with Content-Disposition:
// attachment, which would just trigger a download inside the iframe instead of showing anything.
watch(previewingMedia, async (media) => {
    previewText.value = null;
    if (!media || previewKind(media.mime_type) !== 'text') return;

    previewTextLoading.value = true;
    try {
        const response = await fetch(media.original_url);
        const text = await response.text();
        previewText.value =
            text.length > PREVIEW_TEXT_LIMIT ? text.slice(0, PREVIEW_TEXT_LIMIT) + '\n\n… файл обрізано, завантажте повністю.' : text;
    } catch {
        previewText.value = 'Не вдалося завантажити вміст файлу.';
    } finally {
        previewTextLoading.value = false;
    }
});

function confirmDeleteAttachment() {
    if (!props.card || !deletingMediaId.value) return;
    deletingAttachment.value = true;
    router.delete(route('kanban.cards.attachments.destroy', [props.board.id, props.card.id, deletingMediaId.value]), {
        preserveScroll: true,
        onFinish: () => {
            deletingAttachment.value = false;
            deletingMediaId.value = null;
        },
    });
}

// Посилання — окремо від файлів: сервер при збереженні тягне <title> цільової сторінки й
// показує його замість «голого» URL (KanbanController::storeLink()).
const newLinkUrl = ref('');
const isAddingLink = ref(false);

function addLink() {
    if (!props.card || !newLinkUrl.value.trim()) return;
    isAddingLink.value = true;
    router.post(
        route('kanban.cards.links.store', [props.board.id, props.card.id]),
        { url: newLinkUrl.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => (newLinkUrl.value = ''),
            onFinish: () => (isAddingLink.value = false),
        },
    );
}

function deleteLink(id: number) {
    if (!props.card) return;
    router.delete(route('kanban.cards.links.destroy', [props.board.id, props.card.id, id]), { preserveScroll: true });
}

function fileIcon(mimeType: string) {
    if (!mimeType) return File;
    if (mimeType.startsWith('image/')) return FileImage;
    if (mimeType.startsWith('video/')) return FileVideo;
    if (mimeType.startsWith('audio/')) return FileAudio;
    if (mimeType.includes('pdf') || mimeType.includes('word')) return FileText;
    if (mimeType.includes('zip') || mimeType.includes('rar')) return FileArchive;
    if (mimeType.includes('spreadsheet') || mimeType.includes('excel') || mimeType.includes('csv')) return FileSpreadsheet;
    if (mimeType.includes('javascript') || mimeType.includes('json') || mimeType.includes('html') || mimeType.includes('css')) return FileCode;
    return File;
}

function formatDateTime(value: string) {
    return new Date(value).toLocaleString('uk-UA', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent v-if="card" class="flex h-[90vh] w-[95vw] max-w-[95vw] flex-col gap-0 overflow-hidden p-0 sm:max-w-[95vw]">
            <DialogTitle class="sr-only">{{ card.title }}</DialogTitle>
            <DialogDescription class="sr-only">Деталі та налаштування картки завдання</DialogDescription>

            <div class="flex shrink-0 items-start justify-between gap-6 border-b border-sidebar-border/70 py-4 pr-14 pl-6 dark:border-sidebar-border">
                <div class="min-w-0 flex-1">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="font-mono text-xs font-medium text-muted-foreground">{{ card.display_key ?? `#${card.id}` }}</span>
                    </div>
                    <button
                        v-if="card.parent && !card.parent.archived_at && !card.parent_board_id"
                        type="button"
                        class="mb-1.5 flex items-center gap-1 text-xs text-muted-foreground hover:text-primary"
                        @click="openCard(card.parent.id)"
                    >
                        <ListTree class="size-3" />
                        Підзавдання картки {{ card.parent_key ?? `#${card.parent.id}` }} — {{ card.parent.title }}
                    </button>
                    <!--
                        Батько на ІНШІЙ дошці — не можемо підмінити картку в модалці "на місці"
                        (KanbanBoard.vue::handleNavigate() шукає лише серед карток ЦІЄЇ дошки),
                        тож лінк веде на саму дошку (закриє цю модалку), не на конкретну картку.
                    -->
                    <Link
                        v-else-if="card.parent && !card.parent.archived_at && card.parent_board_id"
                        :href="route('kanban.show', card.parent_board_id)"
                        class="mb-1.5 flex items-center gap-1 text-xs text-muted-foreground hover:text-primary"
                    >
                        <ListTree class="size-3" />
                        Підзавдання картки {{ card.parent_key ?? `#${card.parent.id}` }} — {{ card.parent.title }}
                        <span class="italic">(дошка «{{ card.parent_board_title }}»)</span>
                    </Link>
                    <p v-else-if="card.parent" class="mb-1.5 flex items-center gap-1 text-xs text-muted-foreground">
                        <ListTree class="size-3" />
                        Підзавдання картки {{ card.parent_key ?? `#${card.parent.id}` }} — {{ card.parent.title }}
                        <span class="italic">(архівовано{{ card.parent_board_title ? `, дошка «${card.parent_board_title}»` : '' }})</span>
                    </p>
                    <div v-if="!isEditing">
                        <h2 class="text-xl leading-tight font-semibold text-foreground">{{ card.title }}</h2>
                    </div>
                    <Input v-else v-model="form.title" class="h-9 text-lg font-semibold" />
                </div>

                <div class="flex shrink-0 items-center gap-2 pt-1">
                    <Button v-if="!isEditing" variant="outline" size="sm" @click="isEditing = true">Редагувати</Button>
                    <template v-else>
                        <Button variant="ghost" size="sm" @click="isEditing = false">Скасувати</Button>
                        <Button size="sm" :disabled="form.processing" @click="updateCard">Зберегти</Button>
                    </template>

                    <Separator orientation="vertical" class="mx-1 h-6" />

                    <Button v-if="canArchive" variant="secondary" size="sm" class="gap-1.5" @click="handleArchive">
                        <CheckCircle2 class="size-3.5" />
                        Виконано / Архів
                    </Button>
                </div>
            </div>

            <div class="flex flex-1 overflow-hidden">
                <div class="flex flex-1 flex-col overflow-hidden border-r border-sidebar-border/70 dark:border-sidebar-border">
                    <Tabs v-model="activeTab" class="flex flex-1 flex-col overflow-hidden">
                        <div class="border-b border-sidebar-border/70 px-6 dark:border-sidebar-border">
                            <TabsList class="h-11 w-full justify-start gap-6 rounded-none bg-transparent p-0">
                                <TabsTrigger
                                    value="details"
                                    class="h-full gap-1.5 rounded-none border-b-2 border-transparent px-0 text-sm font-medium data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                                >
                                    <FileText class="size-4" /> Деталі
                                </TabsTrigger>
                                <TabsTrigger
                                    value="comments"
                                    class="h-full gap-1.5 rounded-none border-b-2 border-transparent px-0 text-sm font-medium data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                                >
                                    <MessageSquare class="size-4" /> Коментарі
                                    <Badge v-if="card.comments_count" variant="outline" class="ml-1 h-5 px-1.5">{{ card.comments_count }}</Badge>
                                </TabsTrigger>
                                <TabsTrigger
                                    value="attachments"
                                    class="h-full gap-1.5 rounded-none border-b-2 border-transparent px-0 text-sm font-medium data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                                >
                                    <Paperclip class="size-4" /> Файли та посилання
                                    <Badge
                                        v-if="(card.media?.length ?? 0) + (card.links?.length ?? 0)"
                                        variant="outline"
                                        class="ml-1 h-5 px-1.5"
                                        >{{ (card.media?.length ?? 0) + (card.links?.length ?? 0) }}</Badge
                                    >
                                </TabsTrigger>
                                <!--
                                    Тільки для картки-БАТЬКА — свідомо один рівень вкладеності (модель/БД глибину не
                                    обмежують, обмеження суто інтерфейсне): підзавдання не показують власний таб
                                    «Підзавдання», щоб не плодити рекурсивну вкладеність.
                                -->
                                <TabsTrigger
                                    v-if="!card.parent_id"
                                    value="subtasks"
                                    class="h-full gap-1.5 rounded-none border-b-2 border-transparent px-0 text-sm font-medium data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                                >
                                    <ListTree class="size-4" /> Підзавдання
                                    <Badge v-if="card.subtasks_count" variant="outline" class="ml-1 h-5 px-1.5"
                                        >{{ card.subtasks_done_count ?? 0 }}/{{ card.subtasks_count }}</Badge
                                    >
                                </TabsTrigger>
                                <TabsTrigger
                                    value="history"
                                    class="h-full gap-1.5 rounded-none border-b-2 border-transparent px-0 text-sm font-medium data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                                >
                                    <History class="size-4" /> Історія
                                </TabsTrigger>
                            </TabsList>
                        </div>

                        <div class="flex-1 overflow-y-auto px-6 py-6">
                            <TabsContent value="details" class="m-0 space-y-8 outline-none">
                                <div class="space-y-3">
                                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        <FileText class="size-3.5 text-primary" /> Опис
                                    </div>
                                    <div v-if="!isEditing">
                                        <p v-if="card.description" class="text-sm leading-relaxed whitespace-pre-wrap text-foreground">
                                            {{ card.description }}
                                        </p>
                                        <p v-else class="text-sm text-muted-foreground italic">Опису ще немає.</p>
                                    </div>
                                    <Textarea v-else v-model="form.description" class="min-h-[160px]" placeholder="Опишіть деталі завдання…" />
                                </div>

                                <Separator />

                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            <Square class="size-3.5 text-primary" /> Чек-лист
                                        </div>
                                        <span
                                            v-if="card.checklists?.length"
                                            class="rounded bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"
                                        >
                                            {{ card.checklists.filter((i) => i.is_completed).length }}/{{ card.checklists.length }}
                                        </span>
                                    </div>

                                    <div class="space-y-1">
                                        <div
                                            v-for="item in card.checklists"
                                            :key="item.id"
                                            class="group flex items-center gap-2.5 rounded-md p-1.5 hover:bg-muted/50"
                                        >
                                            <button
                                                type="button"
                                                class="shrink-0"
                                                :class="item.is_completed ? 'text-success-foreground' : 'text-muted-foreground hover:text-primary'"
                                                @click="toggleChecklistItem(item)"
                                            >
                                                <CheckCircle2 v-if="item.is_completed" class="size-[18px]" />
                                                <Square v-else class="size-[18px]" />
                                            </button>
                                            <template v-if="editingChecklistId === item.id">
                                                <Input
                                                    v-model="editingChecklistTitle"
                                                    class="h-7 flex-1 text-sm"
                                                    autofocus
                                                    @keydown.enter.prevent="saveChecklistItem(item)"
                                                    @keydown.esc="cancelEditChecklistItem"
                                                />
                                                <button
                                                    type="button"
                                                    class="shrink-0 p-1 text-muted-foreground hover:text-primary"
                                                    @click="saveChecklistItem(item)"
                                                >
                                                    <Check class="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="shrink-0 p-1 text-muted-foreground hover:text-destructive"
                                                    @click="cancelEditChecklistItem"
                                                >
                                                    <X class="size-3.5" />
                                                </button>
                                            </template>
                                            <template v-else>
                                                <span
                                                    class="flex-1 cursor-text text-sm"
                                                    :class="item.is_completed ? 'text-muted-foreground line-through' : 'text-foreground'"
                                                    @click="startEditChecklistItem(item)"
                                                >
                                                    {{ item.title }}
                                                </span>
                                                <button
                                                    type="button"
                                                    class="p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-primary"
                                                    @click="startEditChecklistItem(item)"
                                                >
                                                    <Pencil class="size-3.5" />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-destructive"
                                                    @click="deleteChecklistItem(item)"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </button>
                                            </template>
                                        </div>

                                        <div class="mt-2 rounded-lg border border-input bg-muted/20">
                                            <Textarea
                                                v-model="newChecklistItem"
                                                placeholder="Додати пункти (кожен з нового рядка)…"
                                                class="min-h-[44px] resize-none border-none bg-transparent focus-visible:ring-0"
                                                @keydown.enter.prevent="addChecklistItem"
                                            />
                                            <div v-if="newChecklistItem.trim()" class="flex justify-end border-t border-input p-2">
                                                <Button size="sm" class="gap-1.5" @click="addChecklistItem"><Plus class="size-3.5" /> Додати</Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </TabsContent>

                            <TabsContent v-if="!card.parent_id" value="subtasks" class="m-0 space-y-3 outline-none">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        <ListTree class="size-3.5 text-primary" /> Підзавдання
                                    </div>
                                    <span
                                        v-if="card.subtasks?.length"
                                        class="rounded bg-primary/10 px-2 py-0.5 text-[11px] font-semibold text-primary"
                                    >
                                        {{ card.subtasks_done_count ?? 0 }}/{{ card.subtasks_count ?? card.subtasks.length }}
                                    </span>
                                </div>

                                <div class="space-y-1.5">
                                    <!--
                                        Клікабельне "на місці" (без переходу) — лише якщо картка активна й на ЦІЙ дошці
                                        (KanbanBoard.vue::handleNavigate() шукає лише серед карток поточної дошки).
                                        Крос-бордове й неархівоване — Link на ІНШУ дошку (закриє модалку). Архівоване —
                                        завжди просто нативний рядок, незалежно від дошки (архівних карток нема серед
                                        активних columns[].cards, перейти "на місці" однаково нема куди).
                                    -->
                                    <component
                                        :is="subtask.archived_at ? 'div' : subtask.board_id ? Link : 'div'"
                                        v-for="subtask in card.subtasks"
                                        :key="subtask.id"
                                        :href="!subtask.archived_at && subtask.board_id ? route('kanban.show', subtask.board_id) : undefined"
                                        class="group flex items-center gap-2.5 rounded-lg border border-sidebar-border/70 p-2.5 dark:border-sidebar-border"
                                        :class="!subtask.archived_at ? 'cursor-pointer hover:border-primary/50 hover:bg-muted/40' : 'opacity-70'"
                                        @click="!subtask.archived_at && !subtask.board_id && openCard(subtask.id)"
                                    >
                                        <CheckCircle2 v-if="subtaskIsDone(subtask)" class="size-[18px] shrink-0 text-success-foreground" />
                                        <Square v-else class="size-[18px] shrink-0 text-muted-foreground" />

                                        <span class="shrink-0 font-mono text-[10px] text-muted-foreground">{{
                                            subtask.display_key ?? `#${subtask.id}`
                                        }}</span>
                                        <span
                                            class="flex-1 truncate text-sm"
                                            :class="subtaskIsDone(subtask) ? 'text-muted-foreground line-through' : 'text-foreground'"
                                        >
                                            {{ subtask.title }}
                                        </span>

                                        <span
                                            v-if="subtask.board_title"
                                            class="shrink-0 rounded bg-primary/10 px-1.5 py-0.5 text-[10px] text-primary"
                                        >
                                            {{ subtask.board_title }}
                                        </span>
                                        <Badge
                                            v-if="subtask.priority"
                                            :variant="priorityBadgeVariant(subtask.priority)"
                                            class="h-5 shrink-0 px-1.5 text-[10px]"
                                        >
                                            {{ priorityLabel(subtask.priority) }}
                                        </Badge>
                                        <span v-if="subtask.column" class="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                                            {{ subtask.column.title }}
                                        </span>

                                        <Avatar v-if="subtask.assignee" size="sm" class="size-5 shrink-0 text-[9px]">
                                            <AvatarFallback class="bg-primary/10 text-[9px] text-primary">{{
                                                getInitials(subtask.assignee.name)
                                            }}</AvatarFallback>
                                        </Avatar>

                                        <button
                                            type="button"
                                            class="shrink-0 p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-destructive"
                                            title="Відв'язати підзавдання"
                                            @click.stop.prevent="unlinkSubtask(subtask)"
                                        >
                                            <X class="size-3.5" />
                                        </button>
                                    </component>

                                    <p v-if="!card.subtasks?.length" class="text-sm text-muted-foreground italic">Підзавдань ще немає.</p>

                                    <div class="mt-2 flex gap-2">
                                        <Input v-model="newSubtaskTitle" placeholder="Назва нового підзавдання…" @keyup.enter="addSubtask" />
                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            class="shrink-0 gap-1.5"
                                            :disabled="!newSubtaskTitle.trim() || isAddingSubtask"
                                            @click="addSubtask"
                                        >
                                            <Plus class="size-3.5" /> Додати
                                        </Button>
                                        <Button size="sm" variant="outline" class="shrink-0 gap-1.5" @click="openLinkSubtask">
                                            <ListTree class="size-3.5" /> Прилінкувати існуючу
                                        </Button>
                                    </div>

                                    <!-- Пошук/лінк існуючої картки — з будь-якої дошки, до якої є доступ (не лише цієї). -->
                                    <div v-if="isLinkingSubtask" class="mt-2 space-y-2 rounded-lg border border-input bg-muted/20 p-3">
                                        <div class="flex items-center justify-between">
                                            <Input v-model="linkSearchQuery" placeholder="Пошук картки за назвою…" class="bg-background" autofocus />
                                            <Button variant="ghost" size="sm" class="ml-2 shrink-0" @click="isLinkingSubtask = false">Закрити</Button>
                                        </div>
                                        <p v-if="linkError" class="text-xs text-destructive">{{ linkError }}</p>
                                        <div class="max-h-56 space-y-1 overflow-y-auto">
                                            <button
                                                v-for="candidate in linkResults"
                                                :key="candidate.id"
                                                type="button"
                                                class="flex w-full items-center gap-2.5 rounded-md p-2 text-left hover:bg-muted"
                                                @click="linkSubtask(candidate)"
                                            >
                                                <span class="shrink-0 font-mono text-[10px] text-muted-foreground">{{
                                                    candidate.display_key ?? `#${candidate.id}`
                                                }}</span>
                                                <span class="flex-1 truncate text-sm text-foreground">{{ candidate.title }}</span>
                                                <span
                                                    v-if="candidate.board_title"
                                                    class="shrink-0 rounded bg-primary/10 px-1.5 py-0.5 text-[10px] text-primary"
                                                >
                                                    {{ candidate.board_title }}
                                                </span>
                                                <span class="shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">{{
                                                    candidate.column_title
                                                }}</span>
                                            </button>
                                            <p v-if="!isSearchingLink && !linkResults.length" class="p-2 text-sm text-muted-foreground italic">
                                                Нічого не знайдено — картка без власного підзавдання/батька і до якої у вас є доступ.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </TabsContent>

                            <TabsContent value="attachments" class="m-0 outline-none">
                                <div class="space-y-8">
                                    <div class="space-y-3">
                                        <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            <Link2 class="size-3.5 text-primary" /> Посилання
                                        </div>

                                        <div class="flex gap-2">
                                            <Input
                                                v-model="newLinkUrl"
                                                type="url"
                                                placeholder="https://…"
                                                class="h-9"
                                                @keyup.enter="addLink"
                                            />
                                            <Button
                                                size="sm"
                                                variant="secondary"
                                                class="shrink-0 gap-1.5"
                                                :disabled="!newLinkUrl.trim() || isAddingLink"
                                                @click="addLink"
                                            >
                                                <Plus class="size-3.5" /> Додати
                                            </Button>
                                        </div>

                                        <div v-if="card.links?.length" class="space-y-1.5">
                                            <div
                                                v-for="link in card.links"
                                                :key="link.id"
                                                class="group flex items-center gap-2.5 rounded-lg border border-sidebar-border/70 p-2.5 dark:border-sidebar-border"
                                            >
                                                <ExternalLink class="size-4 shrink-0 text-muted-foreground" />
                                                <a
                                                    :href="link.url"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="min-w-0 flex-1 truncate text-sm text-foreground hover:text-primary hover:underline"
                                                    :title="link.url"
                                                >
                                                    {{ link.title || link.url }}
                                                </a>
                                                <button
                                                    type="button"
                                                    class="shrink-0 p-1 text-muted-foreground opacity-0 group-hover:opacity-100 hover:text-destructive"
                                                    @click="deleteLink(link.id)"
                                                >
                                                    <Trash2 class="size-3.5" />
                                                </button>
                                            </div>
                                        </div>
                                        <p v-else class="text-sm text-muted-foreground italic">Посилань ще немає.</p>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                            <Paperclip class="size-3.5 text-primary" /> Файли
                                        </div>
                                        <input ref="cardFileInput" type="file" multiple class="hidden" @change="handleCardFileChange" />
                                        <Button size="sm" class="gap-1.5" @click="triggerCardFileInput"><Plus class="size-3.5" /> Додати файл</Button>
                                    </div>

                                    <div v-if="card.media?.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div
                                            v-for="media in card.media"
                                            :key="media.id"
                                            class="group flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                                            :class="previewKind(media.mime_type) ? 'cursor-pointer' : ''"
                                            @click="previewKind(media.mime_type) && (previewingMedia = media)"
                                        >
                                            <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                                <component :is="fileIcon(media.mime_type)" class="size-5" />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-foreground" :title="media.file_name">
                                                    {{ media.file_name }}
                                                </p>
                                                <p class="text-[11px] text-muted-foreground">
                                                    {{ (media.size / 1024).toFixed(0) }} KB · {{ formatDateTime(media.created_at) }}
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100">
                                                <button
                                                    v-if="previewKind(media.mime_type)"
                                                    type="button"
                                                    class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-primary"
                                                    @click.stop="previewingMedia = media"
                                                >
                                                    <Eye class="size-4" />
                                                </button>
                                                <a
                                                    :href="media.original_url"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-primary"
                                                    @click.stop
                                                >
                                                    <Download class="size-4" />
                                                </a>
                                                <button
                                                    type="button"
                                                    class="flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-destructive"
                                                    @click.stop="deletingMediaId = media.id"
                                                >
                                                    <Trash2 class="size-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-else
                                        class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-border py-16 text-muted-foreground"
                                    >
                                        <Paperclip class="mb-3 size-10 opacity-20" />
                                        <p class="text-sm font-medium">Немає прикріплених файлів</p>
                                    </div>
                                </div>
                            </TabsContent>

                            <TabsContent value="comments" class="m-0 space-y-6 outline-none">
                                <div class="flex gap-3">
                                    <Avatar size="sm" class="mt-0.5 size-8 shrink-0">
                                        <AvatarFallback class="bg-primary/10 text-xs text-primary">{{
                                            getInitials(currentUser.name)
                                        }}</AvatarFallback>
                                    </Avatar>
                                    <div class="flex-1 space-y-2">
                                        <Textarea v-model="commentContent" placeholder="Напишіть коментар…" class="min-h-[80px]" />
                                        <div class="flex justify-end">
                                            <Button size="sm" class="gap-1.5" :disabled="!commentContent.trim()" @click="postComment">
                                                Надіслати <Send class="size-3.5" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <Separator />

                                <div class="space-y-6">
                                    <div v-for="comment in card.comments" :key="comment.id" class="flex gap-3">
                                        <Avatar size="sm" class="mt-0.5 size-8 shrink-0">
                                            <AvatarFallback class="bg-primary/10 text-xs text-primary">{{
                                                getInitials(comment.user?.name ?? '')
                                            }}</AvatarFallback>
                                        </Avatar>
                                        <div class="flex-1 space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-semibold text-foreground">{{ comment.user?.name }}</span>
                                                <span class="text-[11px] text-muted-foreground">{{ formatDateTime(comment.created_at) }}</span>
                                            </div>
                                            <div
                                                class="rounded-xl border border-sidebar-border/70 bg-muted/30 p-3 text-sm whitespace-pre-wrap text-foreground dark:border-sidebar-border"
                                            >
                                                {{ comment.content }}
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="!card.comments?.length" class="flex flex-col items-center justify-center py-10 text-muted-foreground">
                                        <MessageSquare class="mb-3 size-10 opacity-10" />
                                        <p class="text-sm">Ще немає коментарів</p>
                                    </div>
                                </div>
                            </TabsContent>

                            <TabsContent value="history" class="m-0 outline-none">
                                <div class="relative space-y-6 border-l border-sidebar-border/70 py-2 pl-8 dark:border-sidebar-border">
                                    <div v-for="activity in card.activities" :key="activity.id" class="relative">
                                        <div
                                            class="absolute top-0 -left-[41px] flex size-6 items-center justify-center rounded-full border border-sidebar-border/70 bg-card dark:border-sidebar-border"
                                        >
                                            <Avatar size="sm" class="size-6"
                                                ><AvatarFallback class="bg-muted text-[9px] text-muted-foreground">{{
                                                    getInitials(activity.user?.name ?? '')
                                                }}</AvatarFallback></Avatar
                                            >
                                        </div>
                                        <p class="text-sm leading-snug text-foreground">
                                            <span class="font-semibold">{{ activity.user?.name ?? 'Система' }}</span>
                                            <span class="ml-1">{{ activity.description }}</span>
                                        </p>
                                        <p class="mt-0.5 font-mono text-[11px] text-muted-foreground">{{ formatDateTime(activity.created_at) }}</p>
                                    </div>
                                    <p v-if="!card.activities?.length" class="text-sm text-muted-foreground italic">Історія змін відсутня.</p>
                                </div>
                            </TabsContent>
                        </div>
                    </Tabs>
                </div>

                <div class="flex w-72 shrink-0 flex-col gap-6 overflow-y-auto bg-muted/10 p-5">
                    <div class="space-y-3">
                        <label class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                            <User class="size-3.5" /> Виконавець
                        </label>
                        <div v-if="!isEditing" class="flex flex-col gap-2">
                            <div class="flex items-center gap-2.5 rounded-lg border border-sidebar-border/70 p-2.5 dark:border-sidebar-border">
                                <Avatar size="sm" class="size-8"
                                    ><AvatarFallback class="bg-primary/10 text-xs text-primary">{{
                                        getInitials(card.assignee?.name ?? '')
                                    }}</AvatarFallback></Avatar
                                >
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-foreground">{{ card.assignee?.name ?? 'Не призначено' }}</p>
                                </div>
                            </div>
                            <Button v-if="card.assigned_to_id !== currentUser.id" variant="ghost" size="sm" class="text-xs" @click="handleAssignMe">
                                Призначити мене
                            </Button>
                        </div>
                        <Select
                            v-else
                            :model-value="form.assigned_to_id ? String(form.assigned_to_id) : undefined"
                            @update:model-value="(v) => (form.assigned_to_id = v ? Number(v) : null)"
                        >
                            <SelectTrigger><SelectValue placeholder="Оберіть виконавця" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="u in availableUsers" :key="u.id" :value="String(u.id)">{{ u.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Separator />

                    <div class="space-y-3">
                        <label class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                            <Clock class="size-3.5" /> Дедлайн
                        </label>
                        <div class="flex items-center gap-2.5 rounded-lg border border-sidebar-border/70 p-2.5 dark:border-sidebar-border">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-md"
                                :class="isOverdue ? 'bg-destructive/10 text-destructive' : 'bg-primary/10 text-primary'"
                            >
                                <Calendar class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium" :class="isOverdue ? 'text-destructive' : 'text-foreground'">{{ formattedDueDate }}</p>
                                <p v-if="isOverdue" class="text-[11px] font-semibold text-destructive">Термін минув</p>
                            </div>
                        </div>
                        <!-- Миттєве встановлення/зміна дедлайну прямо тут, без "Редагувати" — той самий
                             підхід, що колір/важливість нижче. -->
                        <div class="flex items-center gap-1.5">
                            <Input
                                :model-value="card.due_date ? card.due_date.slice(0, 10) : ''"
                                type="date"
                                :disabled="isSettingDueDate"
                                class="h-8 text-sm"
                                @update:model-value="(v) => setCardDueDate((v as string) || null)"
                            />
                            <button
                                v-if="card.due_date"
                                type="button"
                                :disabled="isSettingDueDate"
                                class="shrink-0 rounded-md p-1.5 text-muted-foreground hover:text-destructive disabled:opacity-50"
                                @click="setCardDueDate(null)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <Separator />

                    <div class="space-y-3">
                        <label class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">Важливість</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="option in PRIORITY_OPTIONS"
                                :key="option.value"
                                type="button"
                                :disabled="isSettingPriority"
                                class="disabled:opacity-50"
                                @click="setCardPriority(option.value)"
                            >
                                <Badge
                                    :variant="card.priority === option.value ? priorityBadgeVariant(option.value) : 'outline'"
                                    class="cursor-pointer px-2 py-1 text-xs transition-transform hover:scale-105"
                                >
                                    {{ option.label }}
                                </Badge>
                            </button>
                            <button
                                type="button"
                                :disabled="isSettingPriority"
                                class="flex items-center rounded-md px-1 text-muted-foreground hover:text-destructive disabled:opacity-50"
                                @click="setCardPriority(null)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>

                    <Separator />

                    <div class="space-y-3">
                        <label class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">Колір картки</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="color in KANBAN_COLORS"
                                :key="color"
                                type="button"
                                :disabled="isSettingColor"
                                class="flex size-6 items-center justify-center rounded-full ring-1 ring-black/10 transition-transform hover:scale-110 disabled:opacity-50"
                                :style="{ backgroundColor: color }"
                                @click="setCardColor(color)"
                            >
                                <Check v-if="card.color === color" class="size-3 text-white" />
                            </button>
                            <button
                                type="button"
                                :disabled="isSettingColor"
                                class="flex size-6 items-center justify-center rounded-full border-2 border-dashed border-muted-foreground/40 text-muted-foreground hover:border-destructive hover:text-destructive disabled:opacity-50"
                                @click="setCardColor(null)"
                            >
                                <X class="size-3" />
                            </button>
                        </div>
                    </div>

                    <Separator />

                    <div class="space-y-3">
                        <div class="space-y-1">
                            <label class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">Створив</label>
                            <div class="flex items-center gap-2 text-xs font-medium text-foreground">
                                <Avatar size="sm" class="size-5"
                                    ><AvatarFallback class="bg-muted text-[9px] text-muted-foreground">{{
                                        getInitials(card.creator?.name ?? '')
                                    }}</AvatarFallback></Avatar
                                >
                                {{ card.creator?.name }}
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">Створено</label>
                            <div class="rounded bg-muted/50 px-2 py-1 font-mono text-[11px] text-foreground">
                                {{ formatDateTime(card.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <ConfirmDeleteDialog
        :open="deletingMediaId !== null"
        :processing="deletingAttachment"
        title="Видалити файл?"
        description="Цю дію неможливо скасувати."
        @update:open="(open) => !open && (deletingMediaId = null)"
        @confirm="confirmDeleteAttachment"
    />

    <Dialog :open="previewingMedia !== null" @update:open="(open) => !open && (previewingMedia = null)">
        <DialogContent class="flex h-[85vh] w-[90vw] max-w-4xl flex-col gap-3 overflow-hidden">
            <DialogTitle class="truncate pr-8">{{ previewingMedia?.file_name }}</DialogTitle>
            <div class="flex flex-1 items-center justify-center overflow-auto rounded-lg bg-muted/30">
                <img
                    v-if="previewingMedia && previewKind(previewingMedia.mime_type) === 'image'"
                    :src="previewingMedia.original_url"
                    :alt="previewingMedia.file_name"
                    class="max-h-full max-w-full object-contain"
                />
                <iframe
                    v-else-if="previewingMedia && previewKind(previewingMedia.mime_type) === 'pdf'"
                    :src="previewingMedia.original_url"
                    class="h-full w-full rounded-lg border-0"
                />
                <video
                    v-else-if="previewingMedia && previewKind(previewingMedia.mime_type) === 'video'"
                    :src="previewingMedia.original_url"
                    controls
                    class="max-h-full max-w-full"
                />
                <audio
                    v-else-if="previewingMedia && previewKind(previewingMedia.mime_type) === 'audio'"
                    :src="previewingMedia.original_url"
                    controls
                    class="w-full px-6"
                />
                <p v-else-if="previewTextLoading" class="text-sm text-muted-foreground">Завантаження…</p>
                <pre
                    v-else-if="previewingMedia && previewKind(previewingMedia.mime_type) === 'text'"
                    class="h-full w-full overflow-auto text-wrap rounded-lg bg-muted/50 p-4 text-left font-mono text-xs whitespace-pre-wrap text-foreground"
                    >{{ previewText }}</pre
                >
            </div>
            <div class="flex justify-end">
                <a
                    :href="previewingMedia?.original_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                >
                    <Download class="size-4" /> Завантажити
                </a>
            </div>
        </DialogContent>
    </Dialog>

    <ConfirmDeleteDialog
        :open="unlinkingSubtask !== null"
        :processing="isUnlinkingSubtask"
        title="Відв'язати підзавдання?"
        :description="`«${unlinkingSubtask?.title}» більше не буде підзавданням цієї картки. Сама картка не видаляється.`"
        @update:open="(open) => !open && (unlinkingSubtask = null)"
        @confirm="confirmUnlinkSubtask"
    />
</template>
