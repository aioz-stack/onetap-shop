<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile app support (incremental, nothing existing changes):
 * - device_tokens: push notification tokens registered by the customer, seller and rider apps.
 * - personal_access_tokens.password_confirmed_at: the API equivalent of the web "confirm password"
 *   step that guards seller finance (per token, so confirming on one phone doesn't unlock another).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('app', 20);
            $table->string('platform', 10);
            $table->string('token', 512);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'app']);
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->timestamp('password_confirmed_at')->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('password_confirmed_at');
        });

        Schema::dropIfExists('device_tokens');
    }
};
