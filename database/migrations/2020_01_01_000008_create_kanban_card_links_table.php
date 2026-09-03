<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kanban_card_links')) {
            return;
        }

        Schema::create('kanban_card_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('kanban_cards')->cascadeOnDelete();
            $table->text('url');
            // Заголовок цільової сторінки, витягнутий один раз при збереженні (best-effort).
            // null — не вдалось витягти; фронтенд тоді показує сам URL.
            $table->string('title')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_card_links');
    }
};
