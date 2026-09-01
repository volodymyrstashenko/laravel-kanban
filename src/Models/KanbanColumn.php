<?php

namespace Thevps\Kanban\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class KanbanColumn extends Model implements Sortable
{
    use HasFactory, SortableTrait;

    protected $fillable = [
        'kanban_board_id',
        'title',
        'order_column',
        'is_done',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }

    public $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    public function buildSortQuery()
    {
        return static::query()->where('kanban_board_id', $this->kanban_board_id);
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'kanban_board_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(KanbanCard::class, 'column_id')->ordered();
    }
}
