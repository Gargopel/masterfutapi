<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_api_token_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 12);
            $table->string('endpoint');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('requested_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'requested_at']);
            $table->index(['user_api_token_id', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_api_request_logs');
    }
};
