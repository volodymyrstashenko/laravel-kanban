<?php

namespace Thevps\Kanban\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Thevps\Kanban\Models\KanbanCard;

/**
 * Fired when a card gains an assignee (on create or on assignee change), EXCEPT when a user
 * assigns the card to themselves. The package deliberately does not notify anyone itself —
 * the host application listens and routes this to Telegram / mail / broadcast / nothing.
 *
 * `$card` is loaded with `column.board` so a listener can build a board link and title
 * without extra queries.
 */
class CardAssigned
{
    use Dispatchable;

    public function __construct(
        public KanbanCard $card,
        public mixed $assignedBy,
    ) {}
}
