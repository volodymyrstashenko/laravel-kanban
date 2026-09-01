// Shared quick-pick palette for Kanban board/card color tagging.
export const KANBAN_COLORS = ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#10b981', '#06b6d4', '#3b82f6', '#8b5cf6', '#d946ef', '#ec4899'];

// Picks readable text (near-black or white) for a solid fill of the given hex color,
// so a fully-painted panel stays legible regardless of which palette color was chosen.
export function getContrastTextColor(hex: string): string {
    const clean = hex.replace('#', '');
    const r = parseInt(clean.substring(0, 2), 16);
    const g = parseInt(clean.substring(2, 4), 16);
    const b = parseInt(clean.substring(4, 6), 16);
    const yiq = (r * 299 + g * 587 + b * 114) / 1000;
    return yiq >= 150 ? '#111827' : '#ffffff';
}
