<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Working hours for the "off-work" automated reply: {"days": [1..7 ISO weekdays], "start": "HH:MM", "end": "HH:MM"}.
 * Incremental and nullable: existing replies are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_auto_replies', function (Blueprint $table) {
            $table->json('schedule')->nullable()->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('shop_auto_replies', function (Blueprint $table) {
            $table->dropColumn('schedule');
        });
    }
};
