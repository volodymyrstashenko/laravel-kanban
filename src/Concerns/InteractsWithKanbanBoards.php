<?php

namespace Thevps\Kanban\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Thevps\Kanban\Models\KanbanBoardMember;

/**
 * Optional convenience trait for the host application's User model. Not required by the
 * package — the controller never calls these — but handy for "my boards" lookups in host code.
 */
trait InteractsWithKanbanBoards
{
    public function kanbanMemberships(): HasMany
    {
        return $this->hasMany(KanbanBoardMember::class, 'user_id');
    }
}
