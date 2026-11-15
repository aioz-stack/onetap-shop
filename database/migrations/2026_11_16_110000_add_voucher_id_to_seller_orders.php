<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shop vouchers apply per seller order, so the voucher is recorded on the seller order.
     */
    public function up(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->foreignId('voucher_id')->nullable()->after('discount')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voucher_id');
        });
    }
};
