<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import { priorityBadgeVariant, priorityLabel } from '@/lib/priority';
import type { KanbanCard } from '@/types';
import { CheckSquare, Clock, CornerDownRight, ListTree, MessageSquare, Paperclip } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        card: KanbanCard;
        /** Підзавдання, чий батько лежить у цій самій колонці — відступ, щоб читалось як підкартка (KanbanColumn.vue::isNestedHere()). */
        indent?: boolean;
    }>(),
    { indent: false },
);

defineEmits<{ click: [card: KanbanCard] }>();

const { getInitials } = useInitials();

const formattedDate = computed(() => {
    if (!props.card.due_date) return null;
    return new Date(props.card.due_date).toLocaleDateString('uk-UA', { day: '2-digit', month: '2-digit' });
});

const isOverdue = computed(() => {
    if (!props.card.due_date) return false;
    return new Date(props.card.due_date) < new Date(new Date().toDateString());
});

const checklistDone = computed(() => props.card.checklists.filter((item) => item.is_completed).length);
</script>

<template>
    <div
        class="group relative mb-2 cursor-grab select-none overflow-hidden rounded-lg border border-sidebar-border/70 bg-card py-2.5 pl-4 pr-3 shadow-sm transition-all hover:border-primary/60 hover:shadow-md active:cursor-grabbing dark:border-white/10 dark:bg-secondary dark:hover:border-primary/60"
        :class="indent ? 'ml-5' : ''"
        @click="$emit('click', card)"
    >
        <!-- Кольорова смуга зліва (на всю висоту картки), не зверху — компактніше й помітніше. -->
        <div class="absolute inset-y-0 left-0 w-1" :style="{ backgroundColor: card.color || 'transparent' }" />

        <div class="mb-1.5 flex items-center justify-between gap-2">
            <span class="flex items-center gap-1 font-mono text-[11px] font-medium text-muted-foreground">
                <CornerDownRight v-if="indent" class="size-3 shrink-0 text-muted-foreground/60" />
                {{ card.display_key ?? `#${card.id}` }}
            </span>
            <div class="flex -space-x-1.5">
                <Tooltip v-if="card.creator">
                    <TooltipTrigger as-child>
                        <Avatar size="sm" class="size-5 border-2 border-card text-[9px] dark:border-secondary">
                            <AvatarFallback class="bg-muted text-[9px] text-muted-foreground">{{ getInitials(card.creator.name) }}</AvatarFallback>
                        </Avatar>
                    </TooltipTrigger>
                    <TooltipContent side="top"
                        ><p class="text-xs">Автор: {{ card.creator.name }}</p></TooltipContent
                    >
                </Tooltip>
                <Tooltip v-if="card.assignee">
                    <TooltipTrigger as-child>
                        <Avatar size="sm" class="size-5 border-2 border-card text-[9px] dark:border-secondary">
                            <AvatarFallback class="bg-primary/15 text-[9px] font-semibold text-primary">{{
                                getInitials(card.assignee.name)
                            }}</AvatarFallback>
                        </Avatar>
                    </TooltipTrigger>
                    <TooltipContent side="top"
                        ><p class="text-xs">Виконавець: {{ card.assignee.name }}</p></TooltipContent
                    >
                </Tooltip>
            </div>
        </div>

        <h4 class="mb-2 line-clamp-2 text-sm font-semibold leading-snug text-foreground">{{ card.title }}</h4>

        <div class="flex flex-wrap items-center gap-1.5 text-xs font-medium text-muted-foreground empty:hidden">
            <Badge v-if="card.priority" :variant="priorityBadgeVariant(card.priority)" class="h-5 px-2 text-[11px] font-semibold">
                {{ priorityLabel(card.priority) }}
            </Badge>
            <span v-if="formattedDate" class="flex items-center gap-1" :class="isOverdue ? 'font-semibold text-destructive' : ''">
                <Clock class="size-3.5" />
                {{ formattedDate }}
            </span>
            <span v-if="card.comments_count" class="flex items-center gap-1">
                <MessageSquare class="size-3.5" />
                {{ card.comments_count }}
            </span>
            <Badge
                v-if="card.checklists?.length"
                variant="outline"
                class="h-5 gap-1 px-2 text-[11px] font-semibold dark:border-white/15"
                :class="checklistDone === card.checklists.length ? 'border-success/50 text-success-foreground' : ''"
            >
                <CheckSquare class="size-3.5" />
                {{ checklistDone }}/{{ card.checklists.length }}
            </Badge>
            <Badge
                v-if="card.subtasks_count"
                variant="outline"
                class="h-5 gap-1 px-2 text-[11px] font-semibold dark:border-white/15"
                :class="card.subtasks_done_count === card.subtasks_count ? 'border-success/50 text-success-foreground' : ''"
            >
                <ListTree class="size-3.5" />
                {{ card.subtasks_done_count ?? 0 }}/{{ card.subtasks_count }}
            </Badge>
            <Paperclip v-if="card.media?.length" class="size-3.5 shrink-0" />
        </div>
    </div>
</template>
