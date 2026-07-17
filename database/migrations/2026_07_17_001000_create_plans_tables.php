<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('allow_all')->default(false);
            $table->unsignedInteger('requests_per_minute')->default(10);
            $table->unsignedInteger('max_active_api_keys')->default(3);
            $table->timestamps();
        });

        Schema::create('plan_access_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type')->index();
            $table->string('region')->nullable()->index();
            $table->foreignId('country_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('league_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['plan_id', 'scope_type']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('is_admin')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::dropIfExists('plan_access_rules');
        Schema::dropIfExists('plans');
    }
};
