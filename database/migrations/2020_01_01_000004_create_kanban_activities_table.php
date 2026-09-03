<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Thevps\Kanban\Kanban;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kanban_activities')) {
            return;
        }

        Schema::create('kanban_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('kanban_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained(Kanban::usersTable())->nullOnDelete();
            $table->string('type');
            $table->string('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kanban_activities');
    }
};
