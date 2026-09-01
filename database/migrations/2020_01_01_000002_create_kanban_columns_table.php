<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kanban_columns')) {
            return;
        }

        Schema::create('kanban_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kanban_board_id')->constrained('kanban_boards')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('order_column')->default(0);
            // Позначає колонку "готово" (наразі інформаційний прапорець, без окремої автоматизації).
            $table->boolean('is_done')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_columns');
    }
};
