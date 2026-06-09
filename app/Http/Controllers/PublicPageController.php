<?php

namespace App\Http\Controllers;

use App\Models\ApiProvider;
use App\Models\League;
use App\Models\SiteSetting;
use App\Models\SportsMatch;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PublicPageController extends Controller
{
    public function home()
    {
        return view('home', [
            'settings' => SiteSetting::homepage(),
            'stats' => [
                'providers' => $this->countTable('api_providers', ApiProvider::class),
                'leagues' => $this->countTable('leagues', League::class),
                'teams' => $this->countTable('teams', Team::class),
                'matches' => $this->countTable('sports_matches', SportsMatch::class),
            ],
        ]);
    }

    public function dashboard()
    {
        return view('user-dashboard', [
            'user' => auth()->user(),
            'metadataUrl' => url('/api/v1/metadata'),
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function countTable(string $table, string $model): int
    {
        return Schema::hasTable($table) ? $model::count() : 0;
    }
}
