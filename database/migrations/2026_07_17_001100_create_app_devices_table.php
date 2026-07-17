<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('device_id')->unique();
            $table->string('name');
            $table->string('platform')->nullable();
            $table->string('app_version')->nullable();
            $table->text('public_key');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        Schema::table('user_api_tokens', function (Blueprint $table) {
            $table->foreignId('app_device_id')->nullable()->after('user_id')->constrained('app_devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_api_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('app_device_id');
        });

        Schema::dropIfExists('app_devices');
    }
};
