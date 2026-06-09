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
use App\Models\ApiProvider;
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
            ->paginate(50);
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
            ->paginate(50);
    }
    public function matches(Request $request)
    {
        return SportsMatch::with(['league:id,name,slug', 'season:id,league_id,year,name', 'homeTeam:id,name,slug,logo_url', 'awayTeam:id,name,slug,logo_url', 'provider:id,name,slug'])
            ->when($request->filled('league_id'), fn ($query) => $query->where('league_id', $request->integer('league_id')))
            ->when($request->filled('season_id'), fn ($query) => $query->where('season_id', $request->integer('season_id')))
            ->when($request->filled('team_id'), fn ($query) => $query->where(fn ($nested) => $nested->where('home_team_id', $request->integer('team_id'))->orWhere('away_team_id', $request->integer('team_id'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', (string) $request->query('status')))
            ->when($request->filled('date_from'), fn ($query) => $query->where('starts_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->where('starts_at', '<=', $request->date('date_to')))
            ->when($request->filled('provider'), fn ($query) => $query->whereHas('provider', fn ($provider) => $provider->where('slug', (string) $request->query('provider'))->orWhere('id', $request->integer('provider'))))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('last_synced_at', '>=', $request->date('updated_since')))
            ->latest('starts_at')
            ->paginate(50);
    }
    public function match(SportsMatch $match) { return $match->load(['league', 'season', 'homeTeam', 'awayTeam', 'provider']); }
    public function standings(Request $request)
    {
        return Standing::with(['league:id,name,slug', 'season:id,league_id,year,name', 'team:id,name,slug,logo_url'])
            ->when($request->filled('league_id'), fn ($query) => $query->where('league_id', $request->integer('league_id')))
            ->when($request->filled('season_id'), fn ($query) => $query->where('season_id', $request->integer('season_id')))
            ->when($request->filled('updated_since'), fn ($query) => $query->where('updated_at', '>=', $request->date('updated_since')))
            ->orderBy('position')
            ->paginate(50);
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
                'last_failed_sync_at' => \App\Models\SyncJob::where('status', 'failed')->max('finished_at'),
                'active_providers_count' => ApiProvider::where('is_active', true)->count(),
                'running_jobs_count' => \App\Models\SyncJob::where('status', 'running')->count(),
                'supported_sync_statuses' => ['pending', 'running', 'completed', 'failed', 'cancelled', 'skipped'],
            ],
            'providers_with_data' => ApiProvider::whereHas('requestLogs', fn ($query) => $query->where('success', true))->get(['id', 'name', 'slug']),
            'supported_languages' => ['pt-BR', 'en', 'es', 'zh'],
        ];
    }
}
