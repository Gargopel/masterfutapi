<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_provider_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('encrypted_key');
            $table->string('key_hint')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('requests_per_minute')->nullable();
            $table->unsignedInteger('requests_per_day')->nullable();
            $table->unsignedInteger('requests_used_today')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('api_provider_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 12);
            $table->string('endpoint');
            $table->unsignedInteger('status_code')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at')->index();
            $table->text('response_excerpt')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('league_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('pending')->index();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->unsignedInteger('total_items')->nullable();
            $table->unsignedInteger('processed_items')->nullable();
            $table->unsignedInteger('created_items')->nullable();
            $table->unsignedInteger('updated_items')->nullable();
            $table->unsignedInteger('failed_items')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('config')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_job_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            $table->string('status');
            $table->string('action')->nullable();
            $table->text('error_message')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['sync_job_items', 'sync_jobs', 'api_request_logs', 'api_provider_keys'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
