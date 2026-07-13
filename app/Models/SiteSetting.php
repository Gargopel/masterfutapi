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
            'brand_name' => 'FutAI',
            'nav_badge' => 'Inteligencia esportiva conectada a dados reais',
            'hero_title' => 'Analise futebol com dados confiaveis dentro do FutAI.',
            'hero_subtitle' => 'O FutAI usa a MasterFut API nos bastidores para entregar ligas, times, partidas, classificacoes e contexto esportivo em uma experiencia simples para analise e tomada de decisao.',
            'hero_image_url' => 'https://images.unsplash.com/photo-1556056504-5c7696c4c28d?auto=format&fit=crop&w=1800&q=85',
            'primary_cta_label' => 'Conhecer o FutAI',
            'primary_cta_url' => '#futai',
            'secondary_cta_label' => 'Ver documentacao',
            'secondary_cta_url' => '/docs',
            'accent_color' => '#16a34a',
            'features' => [
                ['title' => 'Analise centralizada', 'description' => 'O app FutAI concentra leitura de jogos, clubes, ligas e contexto esportivo em um fluxo unico.'],
                ['title' => 'Dados MasterFut nos bastidores', 'description' => 'A MasterFut API alimenta o app com uma base estruturada, monitorada e pronta para evoluir.'],
                ['title' => 'Pronto para crescer', 'description' => 'A arquitetura separa produto, API, chaves e consumo para permitir planos, limites e novas integracoes.'],
            ],
        ];
    }

    public static function homepage(): array
    {
        if (! Schema::hasTable('site_settings')) {
            return static::homepageDefaults();
        }

        $stored = static::where('key', 'homepage')->value('value') ?? [];

        $settings = array_replace_recursive(static::homepageDefaults(), is_array($stored) ? $stored : []);

        return static::withoutPublicAuthCta($settings);
    }

    public static function updateHomepage(array $value): array
    {
        $settings = array_replace_recursive(static::homepage(), $value);
        $settings = static::withoutPublicAuthCta($settings);
        static::updateOrCreate(['key' => 'homepage'], ['value' => $settings]);

        return $settings;
    }

    private static function withoutPublicAuthCta(array $settings): array
    {
        if (in_array($settings['primary_cta_url'] ?? '', ['/register', '/login'], true)) {
            $defaults = static::homepageDefaults();
            $settings['primary_cta_label'] = $defaults['primary_cta_label'];
            $settings['primary_cta_url'] = $defaults['primary_cta_url'];
        }

        return $settings;
    }
}
