<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thevps\Kanban\Kanban;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kanban_cards')) {
            return;
        }

        Schema::create('kanban_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('column_id')->constrained('kanban_columns')->cascadeOnDelete();
            // Субтаски — повноцінні KanbanCard з parent_id. cascadeOnDelete — як і всюди в
            // цій таблиці: видалили батьківську картку, підзавдання йдуть з нею.
            $table->foreignId('parent_id')->nullable()->constrained('kanban_cards')->cascadeOnDelete();
            // Порядковий номер картки В МЕЖАХ ЇЇ ДОШКИ (не глобальний id) — разом із
            // kanban_boards.code формує ключ ADM-0001.
            $table->unsignedInteger('number')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            // Окремо від вільного color: фіксовані рівні важливості low | high | asap.
            $table->string('priority')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('created_by_id')->constrained(Kanban::usersTable())->cascadeOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained(Kanban::usersTable())->nullOnDelete();
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_cards');
    }
};
