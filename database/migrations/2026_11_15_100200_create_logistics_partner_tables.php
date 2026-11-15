<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('address')->nullable();
            $table->decimal('base_delivery_fee', 14, 2)->default(0);
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_partner_id')->constrained()->cascadeOnDelete();
            $table->string('region', 100);
            $table->string('province', 100);
            $table->string('city', 100);
            $table->string('barangay', 100)->nullable();
            $table->decimal('delivery_fee', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['logistics_partner_id', 'city', 'barangay']);
            $table->index(['city', 'barangay']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_areas');
        Schema::dropIfExists('logistics_partners');
    }
};
