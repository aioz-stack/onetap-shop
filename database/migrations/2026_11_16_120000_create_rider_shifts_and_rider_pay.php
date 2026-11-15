<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rider shift log (sign in / sign out) and the per-delivery rider pay each partner sets.
     */
    public function up(): void
    {
        Schema::create('rider_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_profile_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->index(['rider_profile_id', 'started_at']);
        });

        Schema::table('logistics_partners', function (Blueprint $table) {
            $table->decimal('rider_fee_per_delivery', 14, 2)->default(0)->after('base_delivery_fee');
        });
    }

    public function down(): void
    {
        Schema::table('logistics_partners', function (Blueprint $table) {
            $table->dropColumn('rider_fee_per_delivery');
        });

        Schema::dropIfExists('rider_shifts');
    }
};
