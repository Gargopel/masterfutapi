<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];
    protected $casts = ['value' => 'array'];

    public static function homepageDefaults(): array
    {
        return [
            'brand_name' => 'MasterFut API',
            'nav_badge' => 'Sports data infrastructure',
            'hero_title' => 'Dados de futebol prontos para produtos, dashboards e automacoes.',
            'hero_subtitle' => 'Uma API esportiva unificada com historico de partidas, standings, times, ligas e atualizacoes confiaveis para produtos digitais.',
            'hero_image_url' => 'https://images.unsplash.com/photo-1556056504-5c7696c4c28d?auto=format&fit=crop&w=1800&q=85',
            'primary_cta_label' => 'Criar conta',
            'primary_cta_url' => '/register',
            'secondary_cta_label' => 'Ver endpoints',
            'secondary_cta_url' => '/api/v1/metadata',
            'accent_color' => '#16a34a',
            'features' => [
                ['title' => 'Dados unificados', 'description' => 'Ligas, times, partidas, standings e estatisticas entregues por uma unica API MasterFut.'],
                ['title' => 'Atualizacoes confiaveis', 'description' => 'Rotinas de coleta, monitoramento e consistencia operando nos bastidores para manter a base pronta.'],
                ['title' => 'API publica v1', 'description' => 'Endpoints versionados para ligas, times, partidas, standings, estatisticas e metadata.'],
            ],
        ];
    }

    public static function homepage(): array
    {
        if (! Schema::hasTable('site_settings')) {
            return static::homepageDefaults();
        }

        $stored = static::where('key', 'homepage')->value('value') ?? [];

        return array_replace_recursive(static::homepageDefaults(), is_array($stored) ? $stored : []);
    }

    public static function updateHomepage(array $value): array
    {
        $settings = array_replace_recursive(static::homepage(), $value);
        static::updateOrCreate(['key' => 'homepage'], ['value' => $settings]);

        return $settings;
    }
}
