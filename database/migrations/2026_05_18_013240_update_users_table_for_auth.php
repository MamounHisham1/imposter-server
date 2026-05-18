<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');

            $table->string('nickname')->unique()->after('id');
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('google_id')->nullable()->unique()->after('password');
            $table->integer('credits')->default(0)->after('google_id');
            $table->boolean('is_admin')->default(false)->after('credits');
            $table->json('avatar')->nullable()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('id');

            $table->dropUnique(['nickname']);
            $table->dropColumn(['nickname', 'google_id', 'credits', 'is_admin', 'avatar']);
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
