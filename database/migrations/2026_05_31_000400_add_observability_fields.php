<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sync_jobs', function (Blueprint $table) {
            $table->foreignId('parent_sync_job_id')->nullable()->after('id')->constrained('sync_jobs')->nullOnDelete();
            $table->timestamp('cancel_requested_at')->nullable()->after('finished_at');
        });

        Schema::table('api_request_logs', function (Blueprint $table) {
            $table->foreignId('sync_job_id')->nullable()->after('api_provider_key_id')->constrained('sync_jobs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('api_request_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sync_job_id');
        });

        Schema::table('sync_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_sync_job_id');
            $table->dropColumn('cancel_requested_at');
        });
    }
};
