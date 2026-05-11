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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 6)->unique();
            $table->string('type')->default('public');
            $table->string('status')->default('waiting');
            $table->integer('max_players')->default(8);
            $table->integer('rounds_per_game')->default(3);
            $table->integer('current_round')->default(0);
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
