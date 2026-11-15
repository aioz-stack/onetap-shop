<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_order_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('rate', 5, 2);
            $table->decimal('base_amount', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('logistics_partner_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 30)->index();
            $table->string('direction', 10);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2)->nullable();
            $table->nullableMorphs('reference');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('pending_balance', 14, 2)->default(0);
            $table->decimal('available_balance', 14, 2)->default(0);
            $table->decimal('total_earned', 14, 2)->default(0);
            $table->decimal('total_withdrawn', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_name');
            $table->text('account_number');
            $table->string('account_last4', 4);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('fee', 14, 2)->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rider_remittances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('logistics_partner_id')->constrained()->restrictOnDelete();
            $table->date('remittance_date');
            $table->decimal('expected_amount', 14, 2);
            $table->decimal('remitted_amount', 14, 2);
            $table->decimal('discrepancy', 14, 2)->default(0);
            $table->string('status', 20)->default('submitted')->index();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['rider_profile_id', 'remittance_date']);
        });

        Schema::create('rider_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('rider_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('rider_remittance_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_due', 14, 2);
            $table->decimal('amount_collected', 14, 2);
            $table->timestamp('collected_at')->nullable();
            $table->string('status', 20)->default('collected')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_collections');
        Schema::dropIfExists('rider_remittances');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('seller_balances');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('commissions');
    }
};
