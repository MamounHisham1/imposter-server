<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aggregate daily stats - one row per day
        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedInteger('games_completed')->default(0);
            $table->unsignedInteger('rooms_created')->default(0);
            $table->unsignedInteger('total_players_joined')->default(0);
            $table->unsignedInteger('unique_players')->default(0);
            $table->unsignedInteger('rounds_played')->default(0);
            $table->unsignedInteger('crew_wins')->default(0);
            $table->unsignedInteger('imposter_wins')->default(0);
            $table->unsignedInteger('ties')->default(0);
            $table->unsignedInteger('imposters_caught')->default(0);
            $table->unsignedInteger('imposters_fled')->default(0);
            $table->timestamps();
        });

        // Individual game records - persisted before room cleanup
        Schema::create('analytics_games', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 6)->index();
            $table->string('language', 5)->default('en');
            $table->string('room_type', 10)->default('public');
            $table->unsignedInteger('player_count');
            $table->unsignedInteger('rounds_played');
            $table->unsignedInteger('rounds_per_game');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index('started_at');
        });

        // Per-round analytics
        Schema::create('analytics_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analytics_game_id')->constrained('analytics_games')->cascadeOnDelete();
            $table->unsignedInteger('round_number');
            $table->string('real_word');
            $table->string('imposter_hint');
            $table->string('winner'); // crew, imposter, tie
            $table->boolean('imposter_caught')->default(false);
            $table->unsignedInteger('player_count')->default(0);
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();

            $table->index('winner');
        });

        // Per-player game stats (accumulated across sessions via nickname)
        Schema::create('analytics_players', function (Blueprint $table) {
            $table->id();
            $table->string('nickname')->index();
            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedInteger('games_won')->default(0);
            $table->unsignedInteger('rounds_as_crew')->default(0);
            $table->unsignedInteger('rounds_as_imposter')->default(0);
            $table->unsignedInteger('wins_as_crew')->default(0);
            $table->unsignedInteger('wins_as_imposter')->default(0);
            $table->unsignedInteger('times_caught_as_imposter')->default(0);
            $table->unsignedInteger('total_score')->default(0);
            $table->timestamps();

            $table->unique('nickname');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_rounds');
        Schema::dropIfExists('analytics_games');
        Schema::dropIfExists('analytics_players');
        Schema::dropIfExists('analytics_daily');
    }
};
