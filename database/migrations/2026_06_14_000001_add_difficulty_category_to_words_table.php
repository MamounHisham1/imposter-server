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
        Schema::table('words', function (Blueprint $table) {
            $table->string('difficulty')->default('medium')->after('hint_ar');
            $table->string('category')->nullable()->after('difficulty');
            $table->boolean('enabled')->default(true)->after('category');

            $table->index(['difficulty', 'enabled']);
            $table->index(['category', 'difficulty', 'enabled']);
            $table->index('word_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('words', function (Blueprint $table) {
            $table->dropIndex(['difficulty', 'enabled']);
            $table->dropIndex(['category', 'difficulty', 'enabled']);
            $table->dropIndex('word_en');

            $table->dropColumn(['difficulty', 'category', 'enabled']);
        });
    }
};
