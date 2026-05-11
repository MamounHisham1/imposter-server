<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->string('winner')->nullable()->after('imposter_id'); // 'crew', 'imposter', 'tie'
            $table->boolean('imposter_caught')->default(false)->after('winner');
            $table->json('vote_tally')->nullable()->after('imposter_caught');
        });
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn(['winner', 'imposter_caught', 'vote_tally']);
        });
    }
};
