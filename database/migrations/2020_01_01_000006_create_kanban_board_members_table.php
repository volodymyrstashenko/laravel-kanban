<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thevps\Kanban\Kanban;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kanban_board_members')) {
            return;
        }

        Schema::create('kanban_board_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kanban_board_id')->constrained('kanban_boards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained(Kanban::usersTable())->cascadeOnDelete();
            $table->enum('role', ['owner', 'editor'])->default('editor');
            $table->timestamps();
            $table->unique(['kanban_board_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_board_members');
    }
};
