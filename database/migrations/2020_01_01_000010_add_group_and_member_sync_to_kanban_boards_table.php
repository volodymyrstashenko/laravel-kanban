<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional linkage for a host that auto-provisions one shared board per some group-like
 * concept of its own (a team, a department, ...) — `group_id` is a plain unsignedBigInteger
 * (no FK, the package doesn't know the host's group model) and `member_sync` records how the
 * host keeps kanban_board_members in step with that group's membership. The package itself
 * never reads or writes either column; it's purely a place for host code to persist state.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kanban_boards', 'group_id')) {
            return;
        }

        Schema::table('kanban_boards', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->index()->after('institution_id');
            $table->enum('member_sync', ['none', 'manual', 'auto'])->default('none')->after('group_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('kanban_boards', 'group_id')) {
            return;
        }

        Schema::table('kanban_boards', function (Blueprint $table) {
            $table->dropColumn(['group_id', 'member_sync']);
        });
    }
};
