<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->unsignedInteger('hint_cycle')->default(1)->after('hint_order');
        });

        Schema::table('hints', function (Blueprint $table) {
            // Drop the foreign key constraint first so the unique index is no longer needed by MySQL
            $table->dropForeign(['round_id']);

            // Drop the existing unique constraint on (round_id, player_id)
            $table->dropUnique(['round_id', 'player_id']);

            // Add hint_cycle column
            $table->unsignedInteger('hint_cycle')->default(1)->after('player_id');

            // New unique constraint: one hint per player per cycle within a round
            $table->unique(['round_id', 'player_id', 'hint_cycle']);

            // Re-add the foreign key constraint
            $table->foreign('round_id')->references('id')->on('rounds')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hints', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['round_id']);

            // Drop the unique constraint
            $table->dropUnique(['round_id', 'player_id', 'hint_cycle']);

            // Drop the column
            $table->dropColumn('hint_cycle');

            // Re-create the original unique constraint
            $table->unique(['round_id', 'player_id']);

            // Re-add the foreign key constraint
            $table->foreign('round_id')->references('id')->on('rounds')->cascadeOnDelete();
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn('hint_cycle');
        });
    }
};
