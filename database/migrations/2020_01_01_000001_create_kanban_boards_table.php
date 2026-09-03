<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Гард: на застосунках, які вже мали цю таблицю до переходу на пакет (напр. Канбан був
        // раніше скопійований прямо в проєкт), таблиця вже існує — міграція пакета має бути no-op.
        if (Schema::hasTable('kanban_boards')) {
            return;
        }

        Schema::create('kanban_boards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // Короткий код дошки ("ADM") — префікс ключа картки ADM-0001. Nullable + unique.
            $table->string('code', 10)->nullable()->unique();
            // Лічильник НАСТУПНОГО номера картки — свідомо не max(number)+1: номер видаленої
            // картки не перевикористовується (як issue-key у Jira).
            $table->unsignedInteger('card_sequence')->default(0);
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->foreignId('created_by_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_boards');
    }
};
