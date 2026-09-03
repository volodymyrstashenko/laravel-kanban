<?php

namespace Thevps\Kanban\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Thevps\Kanban\Kanban;

class KanbanCardLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_id',
        'url',
        'title',
        'created_by_id',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(KanbanCard::class, 'card_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Kanban::userModel(), 'created_by_id');
    }
}
