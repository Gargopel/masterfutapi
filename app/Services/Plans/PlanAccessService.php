<?php

namespace App\Services\Plans;

use App\Models\League;
use App\Models\Plan;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PlanAccessService
{
    private const AMERICAS_COUNTRY_CODES = [
        'AR', 'BO', 'BR', 'CL', 'CO', 'EC', 'FK', 'GF', 'GY', 'PY', 'PE', 'SR', 'UY', 'VE',
        'AI', 'AG', 'AW', 'BS', 'BB', 'BZ', 'BM', 'VG', 'CA', 'KY', 'CR', 'CU', 'CW', 'DM',
        'DO', 'SV', 'GL', 'GD', 'GP', 'GT', 'HT', 'HN', 'JM', 'MQ', 'MX', 'MS', 'NI', 'PA',
        'PR', 'BL', 'KN', 'LC', 'MF', 'PM', 'VC', 'SX', 'TT', 'TC', 'US', 'VI',
    ];

    public function planFor(?User $user): ?Plan
    {
        if (! $user) {
            return null;
        }

        return $user->plan()->with('accessRules')->first()
            ?? Plan::query()->with('accessRules')->where('is_default', true)->where('is_active', true)->first();
    }

    public function hasRestrictions(?User $user): bool
    {
        $plan = $this->planFor($user);

        return (bool) $plan && ! $plan->allow_all && $plan->accessRules->isNotEmpty();
    }

    public function allowedLeagueIds(?User $user): ?Collection
    {
        $plan = $this->planFor($user);

        if (! $plan || $plan->allow_all || $plan->accessRules->isEmpty()) {
            return null;
        }

        $ids = collect();

        foreach ($plan->accessRules as $rule) {
            if ($rule->scope_type === 'region' && $rule->region === 'americas') {
                $ids = $ids->merge(League::whereHas('country', fn (Builder $country) => $country->whereIn('code', self::AMERICAS_COUNTRY_CODES))->pluck('id'));
            }

            if ($rule->scope_type === 'country' && $rule->country_id) {
                $ids = $ids->merge(League::where('country_id', $rule->country_id)->pluck('id'));
            }

            if ($rule->scope_type === 'league' && $rule->league_id) {
                $ids->push($rule->league_id);
            }

            if ($rule->scope_type === 'season' && $rule->season_id) {
                $leagueId = Season::whereKey($rule->season_id)->value('league_id');
                if ($leagueId) {
                    $ids->push($leagueId);
                }
            }
        }

        return $ids->filter()->unique()->values();
    }

    public function allowedSeasonIds(?User $user): ?Collection
    {
        $plan = $this->planFor($user);

        if (! $plan || $plan->allow_all || $plan->accessRules->isEmpty()) {
            return null;
        }

        $seasonRules = $plan->accessRules->where('scope_type', 'season')->pluck('season_id')->filter()->unique()->values();

        return $seasonRules->isEmpty() ? null : $seasonRules;
    }

    public function applyLeagueScope(Builder $query, ?User $user, string $column = 'league_id'): Builder
    {
        $leagueIds = $this->allowedLeagueIds($user);

        if ($leagueIds !== null) {
            $query->whereIn($column, $leagueIds);
        }

        return $query;
    }

    public function applySeasonScope(Builder $query, ?User $user, string $column = 'season_id'): Builder
    {
        $seasonIds = $this->allowedSeasonIds($user);

        if ($seasonIds !== null) {
            $query->whereIn($column, $seasonIds);
        }

        return $query;
    }

    public function canAccessMatch(?User $user, ?int $leagueId = null, ?int $seasonId = null): bool
    {
        $leagueIds = $this->allowedLeagueIds($user);
        $seasonIds = $this->allowedSeasonIds($user);

        if ($leagueIds !== null && (! $leagueId || ! $leagueIds->contains($leagueId))) {
            return false;
        }

        if ($seasonIds !== null && (! $seasonId || ! $seasonIds->contains($seasonId))) {
            return false;
        }

        return true;
    }
}
