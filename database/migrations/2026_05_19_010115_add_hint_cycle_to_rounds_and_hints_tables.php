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
            // Drop the existing unique constraint on (round_id, player_id)
            $table->dropUnique(['round_id', 'player_id']);

            // Add hint_cycle column
            $table->unsignedInteger('hint_cycle')->default(1)->after('player_id');

            // New unique constraint: one hint per player per cycle within a round
            $table->unique(['round_id', 'player_id', 'hint_cycle']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hints', function (Blueprint $table) {
            $table->dropUnique(['round_id', 'player_id', 'hint_cycle']);
            $table->dropColumn('hint_cycle');
            $table->unique(['round_id', 'player_id']);
        });

        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn('hint_cycle');
        });
    }
};
