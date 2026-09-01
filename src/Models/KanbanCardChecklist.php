<?php

namespace Thevps\Kanban\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class KanbanCardChecklist extends Model implements Sortable
{
    use HasFactory, SortableTrait;

    protected $fillable = [
        'card_id',
        'title',
        'is_completed',
        'order_column',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
        ];
    }

    public $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    public function buildSortQuery()
    {
        return static::query()->where('card_id', $this->card_id);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(KanbanCard::class, 'card_id');
    }
}
