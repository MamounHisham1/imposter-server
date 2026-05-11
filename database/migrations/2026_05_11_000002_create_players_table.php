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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('nickname');
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->boolean('is_ready')->default(false);
            $table->boolean('is_imposter')->default(false);
            $table->integer('score')->default(0);
            $table->string('session_id')->nullable();
            $table->timestamps();

            $table->unique(['nickname', 'room_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
