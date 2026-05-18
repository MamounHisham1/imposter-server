<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_purchased_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_type');
            $table->string('item_id');
            $table->string('layer')->nullable();
            $table->integer('price_paid');
            $table->timestamp('purchased_at')->nullable();

            $table->unique(['user_id', 'item_type', 'item_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_purchased_items');
    }
};
