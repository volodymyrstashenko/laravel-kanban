<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kanban_comments')) {
            return;
        }

        Schema::create('kanban_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('kanban_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_comments');
    }
};
