<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Individual page views / impressions
        Schema::create('analytics_page_views', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 36)->index();
            $table->string('session_id', 40)->index();
            $table->string('page', 100)->index();
            $table->string('referrer', 500)->nullable();
            $table->string('device_type', 10)->nullable();
            $table->string('country', 2)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        // Unique visitors - one row per visitor_id (cookie-based)
        Schema::create('analytics_visitors', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 36)->unique();
            $table->string('first_referrer', 500)->nullable();
            $table->unsignedInteger('visit_count')->default(1);
            $table->unsignedInteger('page_view_count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        // Daily visitor aggregates
        Schema::create('analytics_visitor_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('new_visitors')->default(0);
            $table->unsignedInteger('returning_visitors')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('bounce_count')->default(0);
            $table->unsignedInteger('game_joins')->default(0);
            $table->unsignedInteger('game_starts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_page_views');
        Schema::dropIfExists('analytics_visitors');
        Schema::dropIfExists('analytics_visitor_daily');
    }
};
