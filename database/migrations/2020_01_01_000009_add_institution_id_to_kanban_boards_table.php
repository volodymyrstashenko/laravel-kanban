<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional multi-tenancy column — see config('kanban.institution_resolver'). Single-tenant
 * hosts never set a resolver, so this column just sits unused (always null). No FK: the
 * package doesn't know the host's tenant model.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('kanban_boards', 'institution_id')) {
            return;
        }

        Schema::table('kanban_boards', function (Blueprint $table) {
            $table->unsignedBigInteger('institution_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('kanban_boards', 'institution_id')) {
            return;
        }

        Schema::table('kanban_boards', function (Blueprint $table) {
            $table->dropColumn('institution_id');
        });
    }
};
