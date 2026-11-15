<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number', 40)->unique();
            $table->foreignId('seller_order_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('logistics_partner_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 30)->index();
            $table->json('pickup_address');
            $table->json('dropoff_address');
            $table->decimal('cod_amount', 14, 2)->default(0);
            $table->decimal('delivery_fee', 14, 2)->default(0);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['logistics_partner_id', 'status']);
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });

        Schema::create('shipment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('note')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('delivery_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_profile_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->default('delivery');
            $table->string('status', 20)->default('assigned')->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['rider_profile_id', 'status']);
        });

        Schema::create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('rider_profile_id')->constrained()->restrictOnDelete();
            $table->string('photo_path');
            $table->string('recipient_name');
            $table->string('recipient_relation', 50)->nullable();
            $table->string('signature_path')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_proofs');
        Schema::dropIfExists('delivery_assignments');
        Schema::dropIfExists('shipment_status_histories');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
