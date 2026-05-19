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
        Schema::create('game_histories', function (Blueprint $table) {
            $table->id();
            $table->string('room_code');
            $table->string('player_nickname');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role')->default('crew'); // crew or imposter
            $table->string('word')->nullable();
            $table->boolean('won')->default(false);
            $table->integer('score')->default(0);
            $table->integer('rounds_played')->default(0);
            $table->timestamps();

            $table->index(['player_nickname']);
            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_histories');
    }
};
