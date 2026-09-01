<?php

namespace Thevps\Kanban\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Thevps\Kanban\Kanban;

class KanbanBoard extends Model implements HasMedia, Sortable
{
    use HasFactory, InteractsWithMedia, SortableTrait;

    protected $fillable = [
        'title',
        'description',
        'color',
        'code',
        'created_by_id',
        'sort_order',
    ];

    // `card_sequence` свідомо НЕ у $fillable — його змінює лише nextCardNumber() нижче,
    // ніколи напряму з форми/запиту.

    /** Код завжди у верхньому регістрі ("adm" → "ADM") — незалежно від того, як ввів користувач. */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => filled($value) ? mb_strtoupper(trim($value)) : null,
        );
    }

    public $sortable = [
        'order_column_name' => 'sort_order',
        'sort_when_creating' => true,
    ];

    /** Стартові колонки для щойно створеної дошки — остання позначена як "готово". */
    public const DEFAULT_COLUMNS = ['В черзі', 'В роботі', 'На перевірці', 'Завершено'];

    /**
     * Масовий insert замість циклу `KanbanColumn::create()` — інакше Sortable-трейт робив би
     * один max()-запит на кожну колонку, хоча порядок тут уже відомий наперед.
     */
    public static function createDefaultColumns(self $board): void
    {
        $now = now();
        $titles = self::DEFAULT_COLUMNS;

        KanbanColumn::insert(array_values(array_map(
            fn ($index, $title) => [
                'kanban_board_id' => $board->id,
                'title' => $title,
                'order_column' => $index,
                'is_done' => $index === count($titles) - 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            array_keys($titles),
            $titles,
        )));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/png', 'image/jpeg', 'image/webp']);
    }

    public function coverUrl(): ?string
    {
        return $this->getFirstMediaUrl('cover') ?: null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Kanban::userModel(), 'created_by_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(KanbanBoardMember::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(KanbanColumn::class)->ordered();
    }

    /**
     * Наступний номер картки на цій дошці (для ключа ADM-0001). `lockForUpdate()` тримає рядок
     * дошки заблокованим до кінця транзакції — без цього дві картки, створені одночасно, могли
     * б отримати той самий номер (просте `$this->card_sequence + 1` в PHP не атомарне саме по
     * собі, на відміну від SQL `card_sequence = card_sequence + 1`).
     */
    public function nextCardNumber(): int
    {
        return DB::transaction(function () {
            $board = self::whereKey($this->id)->lockForUpdate()->first();
            $board->increment('card_sequence');

            return $board->card_sequence;
        });
    }
}
