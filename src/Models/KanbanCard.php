<?php

namespace Thevps\Kanban\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Thevps\Kanban\Kanban;

class KanbanCard extends Model implements HasMedia, Sortable
{
    use HasFactory, InteractsWithMedia, SortableTrait;

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_ASAP = 'asap';

    protected $fillable = [
        'column_id',
        'parent_id',
        'number',
        'title',
        'description',
        'color',
        'priority',
        'due_date',
        'created_by_id',
        'assigned_to_id',
        'order_column',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    public function buildSortQuery()
    {
        return static::query()->where('column_id', $this->column_id);
    }

    /** Довільні файли, прикріплені прямо до картки (без окремих вкладень у коментарях). */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(KanbanColumn::class, 'column_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Kanban::userModel(), 'created_by_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Kanban::userModel(), 'assigned_to_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(KanbanComment::class, 'card_id')->latest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(KanbanActivity::class, 'card_id')->latest();
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(KanbanCardChecklist::class, 'card_id')->ordered();
    }

    /** Батьківська картка, якщо ця картка — підзавдання. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Підзавдання — звичайні KanbanCard з parent_id = ця картка. Свідомо лише ОДИН рівень у
     * UI (CardDetailsModal не показує таб «Підзавдання» для картки, яка сама є підзавданням) —
     * модель/БД глибину не обмежують, обмеження суто інтерфейсне, щоб не плодити рекурсивну
     * вкладеність.
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }
}
