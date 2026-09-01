<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kanban_card_checklists')) {
            return;
        }

        Schema::create('kanban_card_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('kanban_cards')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_completed')->default(false);
            $table->unsignedInteger('order_column')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_card_checklists');
    }
};
