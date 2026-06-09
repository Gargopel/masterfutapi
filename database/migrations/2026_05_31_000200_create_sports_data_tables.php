<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->index();
            $table->string('flag_url')->nullable();
            $table->timestamps();
        });

        Schema::create('api_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('football');
            $table->string('base_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('docs_url')->nullable();
            $table->string('developer_url')->nullable();
            $table->boolean('is_active')->default(false);
            $table->string('status')->default('inactive')->index();
            $table->unsignedInteger('rate_limit_per_minute')->nullable();
            $table->unsignedInteger('rate_limit_per_day')->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->json('config')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('external_provider_id')->nullable()->constrained('api_providers')->nullOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('type')->nullable();
            $table->string('logo_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('year');
            $table->string('name')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('external_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('external_provider_id')->nullable()->constrained('api_providers')->nullOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->string('name');
            $table->string('short_name')->nullable();
            $table->string('slug')->index();
            $table->string('logo_url')->nullable();
            $table->unsignedInteger('founded')->nullable();
            $table->string('venue_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sport_id')->constrained()->cascadeOnDelete();
            $table->foreignId('league_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('external_provider_id')->nullable()->constrained('api_providers')->nullOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->dateTime('starts_at');
            $table->string('status')->index();
            $table->unsignedInteger('minute')->nullable();
            $table->integer('home_score')->nullable();
            $table->integer('away_score')->nullable();
            $table->string('venue')->nullable();
            $table->string('timezone')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('match_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('player_name')->nullable();
            $table->string('event_type');
            $table->unsignedInteger('minute')->nullable();
            $table->unsignedInteger('extra_minute')->nullable();
            $table->text('description')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('match_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_id')->constrained('matches')->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('shots')->nullable();
            $table->integer('shots_on_target')->nullable();
            $table->integer('corners')->nullable();
            $table->integer('yellow_cards')->nullable();
            $table->integer('red_cards')->nullable();
            $table->decimal('possession', 5, 2)->nullable();
            $table->decimal('expected_goals', 6, 2)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('standings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->integer('position')->nullable();
            $table->integer('points')->nullable();
            $table->integer('played')->nullable();
            $table->integer('won')->nullable();
            $table->integer('draw')->nullable();
            $table->integer('lost')->nullable();
            $table->integer('goals_for')->nullable();
            $table->integer('goals_against')->nullable();
            $table->integer('goal_difference')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('external_id')->nullable()->index();
            $table->date('birth_date')->nullable();
            $table->string('position')->nullable();
            $table->string('photo_url')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('api_providers')->cascadeOnDelete();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('external_id')->index();
            $table->string('external_name')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['provider_mappings', 'players', 'standings', 'match_statistics', 'match_events', 'matches', 'teams', 'seasons', 'leagues', 'api_providers', 'countries', 'sports'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
