<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sync_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sport_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('league_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('cron_expression')->nullable();
            $table->string('frequency')->nullable();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->foreignId('last_sync_job_id')->nullable()->constrained('sync_jobs')->nullOnDelete();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::table('sync_jobs', function (Blueprint $table) {
            $table->foreignId('sync_schedule_id')->nullable()->after('parent_sync_job_id')->constrained('sync_schedules')->nullOnDelete();
            $table->string('source')->default('manual')->after('type');
            $table->boolean('is_incremental')->default(false)->after('source');
        });

        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('severity')->default('info');
            $table->string('title');
            $table->text('message');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('sync_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sync_schedule_id');
            $table->dropColumn(['source', 'is_incremental']);
        });

        Schema::dropIfExists('system_alerts');
        Schema::dropIfExists('sync_schedules');
    }
};
