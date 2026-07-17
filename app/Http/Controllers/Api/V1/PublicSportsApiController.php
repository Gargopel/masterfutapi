<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\League;
use App\Models\Season;
use App\Models\Sport;
use App\Models\SportsMatch;
use App\Models\Standing;
use App\Models\Team;
use App\Services\Plans\PlanAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PublicSportsApiController extends Controller
{
    public function __construct(private readonly PlanAccessService $planAccess) {}

    public function sports() { return Sport::where('is_active', true)->orderBy('name')->paginate(50); }
    public function countries(Request $request)
    {
        $leagueIds = $this->planAccess->allowedLeagueIds($request->user());

        return Country::query()
            ->when($leagueIds !== null, fn ($query) => $query->whereHas('leagues', fn ($leagues) => $leagues->whereIn('id', $leagueIds)))
            ->orderBy('name')
            ->paginate(100);
    }
    public function leagues(Request $request)
    {
        return League::with(['sport:id,name,slug', 'country:id,name,code'])
            ->tap(fn ($query) => $this->planAccess->applyLeagueScope($query, $request->user(), 'id'))
            ->when($request->filled('sport'), fn ($query) => $query->whereHas('sport', fn ($sport) => $sport->where('slug', (string) $request->query('sport'))->orWhere('id', $request->integer('sport'))))
            ->when($request->filled('country'), fn ($query) => $query->whereHas('country', fn ($country) => $country->where('code', (string) $request->query('country'))->orWhere('id', $request->integer('country'))))
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('updated_at', '>=', $request->date('updated_since')))
            ->orderBy('name')
            ->paginate(50)
            ->through(fn (League $league) => $this->hideInternalFields($league));
    }
    public function seasons(Request $request)
    {
        return Season::query()
            ->tap(fn ($query) => $this->planAccess->applyLeagueScope($query, $request->user()))
            ->tap(fn ($query) => $this->planAccess->applySeasonScope($query, $request->user(), 'id'))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('updated_at', '>=', $request->date('updated_since')))
            ->latest('year')
            ->paginate(50);
    }
    public function teams(Request $request)
    {
        $leagueIds = $this->planAccess->allowedLeagueIds($request->user());
        $seasonIds = $this->planAccess->allowedSeasonIds($request->user());

        return Team::query()
            ->when($request->filled('sport'), fn ($query) => $query->whereHas('sport', fn ($sport) => $sport->where('slug', (string) $request->query('sport'))->orWhere('id', $request->integer('sport'))))
            ->when($request->filled('country'), fn ($query) => $query->whereHas('country', fn ($country) => $country->where('code', (string) $request->query('country'))->orWhere('id', $request->integer('country'))))
            ->when($leagueIds !== null, fn ($query) => $query->where(fn ($nested) => $nested
                ->whereHas('homeMatches', fn ($matches) => $matches->whereIn('league_id', $leagueIds)->when($seasonIds !== null, fn ($scoped) => $scoped->whereIn('season_id', $seasonIds)))
                ->orWhereHas('awayMatches', fn ($matches) => $matches->whereIn('league_id', $leagueIds)->when($seasonIds !== null, fn ($scoped) => $scoped->whereIn('season_id', $seasonIds)))))
            ->when($request->filled('league_id'), fn ($query) => $query->where(fn ($nested) => $nested
                ->whereHas('homeMatches', fn ($matches) => $matches->where('league_id', $request->integer('league_id')))
                ->orWhereHas('awayMatches', fn ($matches) => $matches->where('league_id', $request->integer('league_id')))))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('updated_at', '>=', $request->date('updated_since')))
            ->orderBy('name')
            ->paginate(50)
            ->through(fn (Team $team) => $this->hideInternalFields($team));
    }
    public function matches(Request $request)
    {
        return SportsMatch::with(['league:id,name,slug', 'season:id,league_id,year,name', 'homeTeam:id,name,slug,logo_url', 'awayTeam:id,name,slug,logo_url'])
            ->tap(fn ($query) => $this->planAccess->applyLeagueScope($query, $request->user()))
            ->tap(fn ($query) => $this->planAccess->applySeasonScope($query, $request->user()))
            ->when($request->filled('league_id'), fn ($query) => $query->where('league_id', $request->integer('league_id')))
            ->when($request->filled('season_id'), fn ($query) => $query->where('season_id', $request->integer('season_id')))
            ->when($request->filled('team_id'), fn ($query) => $query->where(fn ($nested) => $nested->where('home_team_id', $request->integer('team_id'))->orWhere('away_team_id', $request->integer('team_id'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->where('starts_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->where('starts_at', '<=', $request->date('date_to')))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('last_synced_at', '>=', $request->date('updated_since')))
            ->latest('starts_at')
            ->paginate(50)
            ->through(fn (SportsMatch $match) => $this->hideInternalFields($match));
    }
    public function match(SportsMatch $match)
    {
        abort_unless($this->planAccess->canAccessMatch(request()->user(), $match->league_id, $match->season_id), 403);

        $match->load([
            'league:id,name,slug',
            'season:id,league_id,year,name',
            'homeTeam:id,name,slug,logo_url',
            'awayTeam:id,name,slug,logo_url',
        ]);

        return $this->hideInternalFields($match);
    }
    public function standings(Request $request)
    {
        return Standing::with(['league:id,name,slug', 'season:id,league_id,year,name', 'team:id,name,slug,logo_url'])
            ->tap(fn ($query) => $this->planAccess->applyLeagueScope($query, $request->user()))
            ->tap(fn ($query) => $this->planAccess->applySeasonScope($query, $request->user()))
            ->when($request->filled('league_id'), fn ($query) => $query->where('league_id', $request->integer('league_id')))
            ->when($request->filled('season_id'), fn ($query) => $query->where('season_id', $request->integer('season_id')))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('updated_at', '>=', $request->date('updated_since')))
            ->orderBy('position')
            ->paginate(50)
            ->through(fn (Standing $standing) => $this->hideInternalFields($standing));
    }

    public function summary(Request $request)
    {
        $matches = SportsMatch::query()
            ->tap(fn ($query) => $this->planAccess->applyLeagueScope($query, $request->user()))
            ->tap(fn ($query) => $this->planAccess->applySeasonScope($query, $request->user()));
        $leagueIds = $this->planAccess->allowedLeagueIds($request->user());
        $seasonIds = $this->planAccess->allowedSeasonIds($request->user());

        return [
            'sports' => Sport::count(),
            'countries' => Country::query()->when($leagueIds !== null, fn ($query) => $query->whereHas('leagues', fn ($leagues) => $leagues->whereIn('id', $leagueIds)))->count(),
            'leagues' => League::query()->when($leagueIds !== null, fn ($query) => $query->whereIn('id', $leagueIds))->count(),
            'seasons' => Season::query()->when($leagueIds !== null, fn ($query) => $query->whereIn('league_id', $leagueIds))->when($seasonIds !== null, fn ($query) => $query->whereIn('id', $seasonIds))->count(),
            'teams' => Team::count(),
            'matches' => (clone $matches)->count(),
            'finished_matches' => (clone $matches)->where('status', 'finished')->count(),
            'future_matches' => (clone $matches)->where('starts_at', '>', now())->count(),
            'standings_rows' => Standing::count(),
            'last_synced_at' => (clone $matches)->max('last_synced_at'),
        ];
    }

    public function metadata(Request $request)
    {
        $matches = SportsMatch::query()
            ->tap(fn ($query) => $this->planAccess->applyLeagueScope($query, $request->user()))
            ->tap(fn ($query) => $this->planAccess->applySeasonScope($query, $request->user()));
        $leagueIds = $this->planAccess->allowedLeagueIds($request->user());
        $seasonIds = $this->planAccess->allowedSeasonIds($request->user());

        return [
            'api_version' => 'v1',
            'plan' => [
                'restricted' => $this->planAccess->hasRestrictions($request->user()),
                'allowed_league_ids' => $leagueIds?->values(),
                'allowed_season_ids' => $seasonIds?->values(),
            ],
            'totals' => [
                'sports' => Sport::count(),
                'leagues' => League::query()->when($leagueIds !== null, fn ($query) => $query->whereIn('id', $leagueIds))->count(),
                'seasons' => Season::query()->when($leagueIds !== null, fn ($query) => $query->whereIn('league_id', $leagueIds))->when($seasonIds !== null, fn ($query) => $query->whereIn('id', $seasonIds))->count(),
                'teams' => Team::count(),
                'matches' => (clone $matches)->count(),
            ],
            'last_sync_at' => (clone $matches)->max('last_synced_at'),
            'freshness' => [
                'last_successful_sync_at' => \App\Models\SyncJob::where('status', 'completed')->max('finished_at'),
                'last_data_refresh_at' => SportsMatch::max('last_synced_at'),
                'running_updates_count' => \App\Models\SyncJob::where('status', 'running')->count(),
            ],
            'supported_languages' => ['pt-BR', 'en', 'es', 'zh'],
        ];
    }

    private function hideInternalFields(Model $model): Model
    {
        return $model->makeHidden([
            'external_provider_id',
            'external_id',
            'raw_payload',
        ]);
    }
}
