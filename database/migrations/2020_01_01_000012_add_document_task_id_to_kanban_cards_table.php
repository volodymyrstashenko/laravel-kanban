<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional linkage for a host that generates cards from some external "task" concept of its
 * own (e.g. an official document/order's action points) and needs to find "the card(s) already
 * created for this task" on re-sync. No FK — the package doesn't know the host's task model —
 * and the package itself never reads or writes this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kanban_cards', 'document_task_id')) {
            return;
        }

        Schema::table('kanban_cards', function (Blueprint $table) {
            $table->unsignedBigInteger('document_task_id')->nullable()->index()->after('parent_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('kanban_cards', 'document_task_id')) {
            return;
        }

        Schema::table('kanban_cards', function (Blueprint $table) {
            $table->dropColumn('document_task_id');
        });
    }
};
