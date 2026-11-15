<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track when a completed order's seller earning moved from pending to available.
     */
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->timestamp('earnings_released_at')->nullable()->after('completed_at');
            $table->index(['status', 'earnings_released_at']);
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'earnings_released_at']);
            $table->dropColumn('earnings_released_at');
        });
    }
};
