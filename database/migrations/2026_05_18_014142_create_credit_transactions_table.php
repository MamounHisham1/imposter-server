<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->integer('balance_after');
            $table->string('type');
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
