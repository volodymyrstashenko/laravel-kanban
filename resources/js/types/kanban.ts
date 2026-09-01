export interface KanbanUserRef {
    id: number;
    name: string;
    email?: string;
}

export interface KanbanMedia {
    id: number;
    file_name: string;
    mime_type: string;
    size: number;
    original_url: string;
    created_at: string;
}

export interface KanbanChecklistItem {
    id: number;
    title: string;
    is_completed: boolean;
}

export interface KanbanComment {
    id: number;
    content: string;
    user: KanbanUserRef | null;
    created_at: string;
}

export interface KanbanActivity {
    id: number;
    type: string;
    description: string;
    user: KanbanUserRef | null;
    created_at: string;
}

/** Полегшена картка-підзавдання, як вона приходить у KanbanCard.subtasks (лише поля прев'ю-рядка). */
export interface KanbanSubtaskRef {
    id: number;
    parent_id: number;
    column_id: number;
    number: number | null;
    display_key: string | null;
    title: string;
    priority: 'low' | 'high' | 'asap' | null;
    assigned_to_id: number | null;
    assignee: KanbanUserRef | null;
    archived_at: string | null;
    column: { id: number; title: string; is_done: boolean } | null;
    /** Назва/id дошки, якщо це підзавдання прилінковане з ІНШОЇ дошки, а не тієї, що переглядається зараз. */
    board_title: string | null;
    board_id: number | null;
}

export interface KanbanCard {
    id: number;
    column_id: number;
    parent_id: number | null;
    /** Батьківська картка (лише якщо ця картка сама — підзавдання). */
    parent: { id: number; number: number | null; title: string; archived_at: string | null } | null;
    /** "ADM-0001" батьківської картки — для беклінку в CardDetailsModal.vue. null, якщо нема батька/коду. */
    parent_key: string | null;
    /** Назва/id дошки батьківської картки, якщо та лежить на ІНШІЙ дошці, а не тій, що переглядається зараз. */
    parent_board_title: string | null;
    parent_board_id: number | null;
    number: number | null;
    /** "ADM-0001" — код дошки + номер, нуль-доповнений (KanbanController::show()). null, якщо в дошки ще нема коду. */
    display_key: string | null;
    title: string;
    description: string | null;
    color: string | null;
    priority: 'low' | 'high' | 'asap' | null;
    due_date: string | null;
    created_by_id: number;
    assigned_to_id: number | null;
    creator: KanbanUserRef | null;
    assignee: KanbanUserRef | null;
    media: KanbanMedia[];
    comments: KanbanComment[];
    comments_count?: number;
    activities: KanbanActivity[];
    checklists: KanbanChecklistItem[];
    subtasks: KanbanSubtaskRef[];
    subtasks_count?: number;
    subtasks_done_count?: number;
    created_at: string;
}

export interface KanbanColumn {
    id: number;
    kanban_board_id: number;
    title: string;
    is_done: boolean;
    cards: KanbanCard[];
}

export interface KanbanBoardMember {
    id: number;
    user_id: number;
    role: 'owner' | 'editor';
    user: KanbanUserRef;
}

export interface KanbanBoard {
    id: number;
    title: string;
    description: string | null;
    color: string | null;
    code: string | null;
    created_by_id: number;
    creator?: KanbanUserRef;
    cover_url: string | null;
    members?: KanbanBoardMember[];
    created_at: string;
}
