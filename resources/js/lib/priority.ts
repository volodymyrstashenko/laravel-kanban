import type { BadgeVariants } from '@/components/ui/badge';

/**
 * Спільний рівень важливості для карток Канбану й звернень (tickets) — той самий сенс і той
 * самий візуал в обох місцях, тож один спільний файл, а не дублювання в lib/tickets.ts і
 * kanban-специфічних місцях.
 */
export type Priority = 'low' | 'high' | 'asap';

export const PRIORITY_OPTIONS: { value: Priority; label: string }[] = [
    { value: 'low', label: 'Низька' },
    { value: 'high', label: 'Висока' },
    { value: 'asap', label: 'Терміново' },
];

const PRIORITY_BADGE_VARIANTS: Record<Priority, NonNullable<BadgeVariants['variant']>> = {
    low: 'outline',
    high: 'warning',
    asap: 'destructive',
};

export function priorityLabel(priority: string | null | undefined): string | null {
    if (!priority) return null;
    return PRIORITY_OPTIONS.find((p) => p.value === priority)?.label ?? priority;
}

export function priorityBadgeVariant(priority: string): NonNullable<BadgeVariants['variant']> {
    return PRIORITY_BADGE_VARIANTS[priority as Priority] ?? 'default';
}
