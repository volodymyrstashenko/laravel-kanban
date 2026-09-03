<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional marker for a host that auto-provisions single-owner boards (e.g. a personal "my
 * tasks" board created the first time it's needed) and wants to tell those apart from regular,
 * user-created boards. The package never sets or reads this itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kanban_boards', 'personal_type')) {
            return;
        }

        Schema::table('kanban_boards', function (Blueprint $table) {
            $table->string('personal_type')->nullable()->after('member_sync');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('kanban_boards', 'personal_type')) {
            return;
        }

        Schema::table('kanban_boards', function (Blueprint $table) {
            $table->dropColumn('personal_type');
        });
    }
};
