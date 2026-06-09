<?php

namespace App\Services\SportsData;

use App\Models\ApiProvider;
use App\Models\Country;
use App\Models\League;
use App\Models\ProviderMapping;
use App\Models\Season;
use App\Models\Sport;
use App\Models\SportsMatch;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SportsDataNormalizer
{
    public function slug(string $value): string
    {
        return Str::slug($value);
    }

    public function football(): Sport
    {
        return Sport::firstOrCreate(['slug' => 'football'], ['name' => 'Football', 'is_active' => true]);
    }

    public function country(?string $name, ?string $code = null, ?string $flagUrl = null): ?Country
    {
        if (! $name && ! $code) {
            return null;
        }

        $identity = $code ? ['code' => $code] : ['name' => $name];

        return Country::updateOrCreate($identity, [
            'name' => $name ?: $code,
            'code' => $code,
            'flag_url' => $flagUrl,
        ]);
    }

    public function league(ApiProvider $provider, array $data): League
    {
        $sport = $data['sport'] ?? $this->football();
        $externalId = (string) $data['external_id'];
        $mapped = $this->findMapped($provider, 'league', $externalId);

        $values = [
            'sport_id' => $sport->id,
            'country_id' => $data['country']?->id ?? null,
            'external_provider_id' => $provider->id,
            'external_id' => $externalId,
            'name' => $data['name'],
            'slug' => $this->slug($data['name']),
            'type' => $data['type'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        $league = $mapped?->entity_id ? League::updateOrCreate(['id' => $mapped->entity_id], $values) : League::updateOrCreate([
            'external_provider_id' => $provider->id,
            'external_id' => $externalId,
        ], $values);

        $this->mapping($provider, 'league', $league->id, $externalId, $league->name, $data['raw_payload'] ?? null);

        return $league;
    }

    public function season(ApiProvider $provider, League $league, int $year, array $data = []): Season
    {
        $externalId = isset($data['external_id']) ? (string) $data['external_id'] : (string) $year;
        $season = Season::updateOrCreate([
            'league_id' => $league->id,
            'year' => $year,
        ], [
            'name' => $data['name'] ?? (string) $year,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_current' => $data['is_current'] ?? false,
            'external_id' => $externalId,
        ]);

        $this->mapping($provider, 'season', $season->id, $league->id.':'.$externalId, $season->name, $data['raw_payload'] ?? null);

        return $season;
    }

    public function team(ApiProvider $provider, array $data): Team
    {
        $sport = $data['sport'] ?? $this->football();
        $externalId = (string) $data['external_id'];
        $mapped = $this->findMapped($provider, 'team', $externalId);

        $values = [
            'sport_id' => $sport->id,
            'country_id' => $data['country']?->id ?? null,
            'external_provider_id' => $provider->id,
            'external_id' => $externalId,
            'name' => $data['name'],
            'short_name' => $data['short_name'] ?? null,
            'slug' => $this->slug($data['name']),
            'logo_url' => $data['logo_url'] ?? null,
            'founded' => $data['founded'] ?? null,
            'venue_name' => $data['venue_name'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ];

        $team = $mapped?->entity_id ? Team::updateOrCreate(['id' => $mapped->entity_id], $values) : Team::updateOrCreate([
            'external_provider_id' => $provider->id,
            'external_id' => $externalId,
        ], $values);

        $this->mapping($provider, 'team', $team->id, $externalId, $team->name, $data['raw_payload'] ?? null);

        return $team;
    }

    public function match(ApiProvider $provider, array $data): SportsMatch
    {
        $externalId = isset($data['external_id']) ? (string) $data['external_id'] : null;
        $startsAt = $this->dateTime($data['starts_at']);
        $mapped = $externalId ? $this->findMapped($provider, 'match', $externalId) : null;

        $values = [
            'sport_id' => $data['sport']->id,
            'league_id' => $data['league']?->id,
            'season_id' => $data['season']?->id,
            'home_team_id' => $data['home_team']?->id,
            'away_team_id' => $data['away_team']?->id,
            'external_provider_id' => $provider->id,
            'external_id' => $externalId,
            'starts_at' => $startsAt,
            'status' => $data['status'] ?? 'unknown',
            'minute' => $data['minute'] ?? null,
            'home_score' => $data['home_score'] ?? null,
            'away_score' => $data['away_score'] ?? null,
            'venue' => $data['venue'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'raw_payload' => $data['raw_payload'] ?? null,
            'last_synced_at' => now(),
        ];

        if ($mapped?->entity_id) {
            $match = SportsMatch::updateOrCreate(['id' => $mapped->entity_id], $values);
        } elseif ($externalId) {
            $match = SportsMatch::updateOrCreate(['external_provider_id' => $provider->id, 'external_id' => $externalId], $values);
        } else {
            $match = SportsMatch::updateOrCreate([
                'league_id' => $values['league_id'],
                'season_id' => $values['season_id'],
                'home_team_id' => $values['home_team_id'],
                'away_team_id' => $values['away_team_id'],
                'starts_at' => $startsAt,
            ], $values);
        }

        if ($externalId) {
            $this->mapping($provider, 'match', $match->id, $externalId, ($data['home_team']?->name ?? 'Home').' vs '.($data['away_team']?->name ?? 'Away'), $data['raw_payload'] ?? null);
        }

        return $match;
    }

    public function mapping(ApiProvider $provider, string $entityType, ?int $entityId, string $externalId, ?string $externalName = null, ?array $rawPayload = null): ProviderMapping
    {
        return ProviderMapping::updateOrCreate([
            'provider_id' => $provider->id,
            'entity_type' => $entityType,
            'external_id' => $externalId,
        ], [
            'entity_id' => $entityId,
            'external_name' => $externalName,
            'raw_payload' => $rawPayload,
        ]);
    }

    public function mapStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'SCHEDULED', 'TIMED', 'NS', 'TBD' => 'scheduled',
            'IN_PLAY', 'PAUSED', 'LIVE', '1H', '2H', 'HT', 'ET', 'BT', 'P', 'SUSP', 'INT' => 'live',
            'FINISHED', 'FT', 'AET', 'PEN' => 'finished',
            'POSTPONED', 'PST' => 'postponed',
            'CANCELLED', 'CANC' => 'cancelled',
            default => 'unknown',
        };
    }

    private function findMapped(ApiProvider $provider, string $entityType, string $externalId): ?ProviderMapping
    {
        return ProviderMapping::where('provider_id', $provider->id)
            ->where('entity_type', $entityType)
            ->where('external_id', $externalId)
            ->first();
    }

    private function dateTime(mixed $value): Carbon
    {
        return $value instanceof Carbon ? $value : Carbon::parse($value);
    }
}
