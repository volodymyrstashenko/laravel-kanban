<?php

namespace Thevps\Kanban\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thevps\Kanban\Kanban;

class KanbanBoardMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'kanban_board_id',
        'user_id',
        'role',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'kanban_board_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Kanban::userModel());
    }
}
