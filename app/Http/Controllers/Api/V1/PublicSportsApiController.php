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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PublicSportsApiController extends Controller
{
    public function sports() { return Sport::where('is_active', true)->orderBy('name')->paginate(50); }
    public function countries() { return Country::orderBy('name')->paginate(100); }
    public function leagues(Request $request)
    {
        return League::with(['sport:id,name,slug', 'country:id,name,code'])
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
            ->when($request->filled('updated_since'), fn ($query) => $query->where('updated_at', '>=', $request->date('updated_since')))
            ->latest('year')
            ->paginate(50);
    }
    public function teams(Request $request)
    {
        return Team::query()
            ->when($request->filled('sport'), fn ($query) => $query->whereHas('sport', fn ($sport) => $sport->where('slug', (string) $request->query('sport'))->orWhere('id', $request->integer('sport'))))
            ->when($request->filled('country'), fn ($query) => $query->whereHas('country', fn ($country) => $country->where('code', (string) $request->query('country'))->orWhere('id', $request->integer('country'))))
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
            ->when($request->filled('league_id'), fn ($query) => $query->where('league_id', $request->integer('league_id')))
            ->when($request->filled('season_id'), fn ($query) => $query->where('season_id', $request->integer('season_id')))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('updated_at', '>=', $request->date('updated_since')))
            ->orderBy('position')
            ->paginate(50)
            ->through(fn (Standing $standing) => $this->hideInternalFields($standing));
    }

    public function summary()
    {
        return [
            'sports' => Sport::count(),
            'countries' => Country::count(),
            'leagues' => League::count(),
            'seasons' => Season::count(),
            'teams' => Team::count(),
            'matches' => SportsMatch::count(),
            'finished_matches' => SportsMatch::where('status', 'finished')->count(),
            'future_matches' => SportsMatch::where('starts_at', '>', now())->count(),
            'standings_rows' => Standing::count(),
            'last_synced_at' => SportsMatch::max('last_synced_at'),
        ];
    }

    public function metadata()
    {
        return [
            'api_version' => 'v1',
            'totals' => [
                'sports' => Sport::count(),
                'leagues' => League::count(),
                'seasons' => Season::count(),
                'teams' => Team::count(),
                'matches' => SportsMatch::count(),
            ],
            'last_sync_at' => SportsMatch::max('last_synced_at'),
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
