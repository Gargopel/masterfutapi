<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Docs - {{ $settings['brand_name'] }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-zinc-950">
    <header class="border-b border-zinc-200 bg-zinc-950 text-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-5 lg:px-8">
            <a href="/" class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded bg-emerald-500 font-black text-zinc-950">MF</span>
                <span class="font-bold">{{ $settings['brand_name'] }}</span>
            </a>
            <nav class="flex items-center gap-2 text-sm">
                <a class="rounded px-3 py-2 text-white/75 hover:text-white" href="/">Home</a>
                <a class="rounded px-3 py-2 text-white/75 hover:text-white" href="/login">Login</a>
                <a class="rounded bg-white px-4 py-2 font-semibold text-zinc-950" href="/register">Criar conta</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="bg-zinc-950 pb-14 pt-8 text-white">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <p class="text-sm font-semibold text-emerald-300">Documentacao v1</p>
                <h1 class="mt-3 max-w-4xl text-4xl font-black md:text-6xl">Integre dados de futebol da MasterFut API.</h1>
                <p class="mt-5 max-w-3xl text-lg leading-8 text-zinc-300">Use endpoints REST para consultar esportes, paises, ligas, temporadas, times, partidas, classificacoes e resumo estatistico.</p>
                <div class="mt-8 rounded border border-white/10 bg-white/10 p-4">
                    <p class="text-sm font-semibold text-zinc-300">Base URL</p>
                    <code class="mt-2 block overflow-x-auto rounded bg-black/40 p-4 text-sm text-emerald-200">{{ $baseUrl }}</code>
                </div>
            </div>
        </section>

        <section class="mx-auto grid max-w-7xl gap-8 px-6 py-12 lg:grid-cols-[260px_1fr] lg:px-8">
            <aside class="hidden lg:block">
                <nav class="sticky top-6 grid gap-2 text-sm">
                    <a class="rounded bg-zinc-100 px-3 py-2 font-semibold" href="#quickstart">Quickstart</a>
                    <a class="rounded px-3 py-2 hover:bg-zinc-100" href="#pagination">Paginacao</a>
                    <a class="rounded px-3 py-2 hover:bg-zinc-100" href="#endpoints">Endpoints</a>
                    <a class="rounded px-3 py-2 hover:bg-zinc-100" href="#examples">Exemplos</a>
                    <a class="rounded px-3 py-2 hover:bg-zinc-100" href="#errors">Erros</a>
                </nav>
            </aside>

            <div class="space-y-10">
                <section id="quickstart" class="rounded border border-zinc-200 p-6">
                    <h2 class="text-2xl font-black">Quickstart</h2>
                    <p class="mt-3 text-zinc-600">A API responde em JSON. Nesta fase inicial os endpoints publicos podem ser acessados diretamente pela URL da MasterFut.</p>
                    <pre class="mt-4 overflow-x-auto rounded bg-zinc-950 p-4 text-sm text-emerald-100"><code>curl "{{ $baseUrl }}/metadata"</code></pre>
                    <pre class="mt-3 overflow-x-auto rounded bg-zinc-950 p-4 text-sm text-emerald-100"><code>fetch('{{ $baseUrl }}/matches?status=finished')
  .then(response => response.json())
  .then(data => console.log(data.data));</code></pre>
                </section>

                <section id="pagination" class="rounded border border-zinc-200 p-6">
                    <h2 class="text-2xl font-black">Paginacao e formato</h2>
                    <p class="mt-3 text-zinc-600">Listagens retornam uma estrutura paginada com `data`, `links` e `meta`. Use o parametro `page` para navegar.</p>
                    <pre class="mt-4 overflow-x-auto rounded bg-zinc-950 p-4 text-sm text-emerald-100"><code>GET {{ $baseUrl }}/teams?page=2</code></pre>
                    <div class="mt-4 grid gap-3 md:grid-cols-3">
                        <div class="rounded bg-zinc-100 p-4"><p class="font-bold">data</p><p class="mt-1 text-sm text-zinc-600">Itens retornados.</p></div>
                        <div class="rounded bg-zinc-100 p-4"><p class="font-bold">links</p><p class="mt-1 text-sm text-zinc-600">URLs de navegacao.</p></div>
                        <div class="rounded bg-zinc-100 p-4"><p class="font-bold">meta</p><p class="mt-1 text-sm text-zinc-600">Pagina atual, total e limites.</p></div>
                    </div>
                </section>

                <section id="endpoints" class="rounded border border-zinc-200 p-6">
                    <h2 class="text-2xl font-black">Endpoints</h2>
                    <div class="mt-5 grid gap-3">
                        @foreach ([
                            ['/metadata', 'Informacoes gerais da API, totais e frescor dos dados.'],
                            ['/sports', 'Lista de esportes disponiveis.'],
                            ['/countries', 'Lista de paises cadastrados.'],
                            ['/leagues', 'Lista de ligas e competicoes. Filtros: sport, country, active, updated_since.'],
                            ['/seasons', 'Temporadas disponiveis. Filtro: updated_since.'],
                            ['/teams', 'Times cadastrados. Filtros: sport, country, league_id, updated_since.'],
                            ['/matches', 'Partidas. Filtros: league_id, season_id, team_id, status, date_from, date_to, updated_since.'],
                            ['/matches/{id}', 'Detalhe de uma partida especifica.'],
                            ['/standings', 'Classificacoes por liga e temporada. Filtros: league_id, season_id, updated_since.'],
                            ['/stats/summary', 'Resumo numerico da base disponivel.'],
                        ] as [$path, $description])
                            <article class="rounded bg-zinc-100 p-4">
                                <code class="font-mono text-sm font-bold">GET {{ $path }}</code>
                                <p class="mt-2 text-sm text-zinc-600">{{ $description }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section id="examples" class="rounded border border-zinc-200 p-6">
                    <h2 class="text-2xl font-black">Exemplos de uso</h2>

                    <div class="mt-5 space-y-5">
                        <div>
                            <h3 class="font-bold">Buscar ligas ativas</h3>
                            <pre class="mt-2 overflow-x-auto rounded bg-zinc-950 p-4 text-sm text-emerald-100"><code>GET {{ $baseUrl }}/leagues?active=1</code></pre>
                        </div>

                        <div>
                            <h3 class="font-bold">Buscar partidas finalizadas de uma liga em um periodo</h3>
                            <pre class="mt-2 overflow-x-auto rounded bg-zinc-950 p-4 text-sm text-emerald-100"><code>GET {{ $baseUrl }}/matches?league_id=1&status=finished&date_from=2026-01-01&date_to=2026-12-31</code></pre>
                        </div>

                        <div>
                            <h3 class="font-bold">Buscar partidas de um time</h3>
                            <pre class="mt-2 overflow-x-auto rounded bg-zinc-950 p-4 text-sm text-emerald-100"><code>GET {{ $baseUrl }}/matches?team_id=10</code></pre>
                        </div>

                        <div>
                            <h3 class="font-bold">Buscar classificacao de uma temporada</h3>
                            <pre class="mt-2 overflow-x-auto rounded bg-zinc-950 p-4 text-sm text-emerald-100"><code>GET {{ $baseUrl }}/standings?league_id=1&season_id=1</code></pre>
                        </div>

                        <div>
                            <h3 class="font-bold">Atualizacoes incrementais</h3>
                            <p class="mt-1 text-sm text-zinc-600">Use `updated_since` para buscar registros alterados depois de uma data.</p>
                            <pre class="mt-2 overflow-x-auto rounded bg-zinc-950 p-4 text-sm text-emerald-100"><code>GET {{ $baseUrl }}/teams?updated_since=2026-06-01</code></pre>
                        </div>
                    </div>
                </section>

                <section id="errors" class="rounded border border-zinc-200 p-6">
                    <h2 class="text-2xl font-black">Codigos e boas praticas</h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div class="rounded bg-zinc-100 p-4"><p class="font-bold">200</p><p class="mt-1 text-sm text-zinc-600">Requisicao processada com sucesso.</p></div>
                        <div class="rounded bg-zinc-100 p-4"><p class="font-bold">404</p><p class="mt-1 text-sm text-zinc-600">Registro ou rota nao encontrada.</p></div>
                        <div class="rounded bg-zinc-100 p-4"><p class="font-bold">422</p><p class="mt-1 text-sm text-zinc-600">Parametro invalido.</p></div>
                        <div class="rounded bg-zinc-100 p-4"><p class="font-bold">500</p><p class="mt-1 text-sm text-zinc-600">Erro inesperado. Tente novamente ou contate suporte.</p></div>
                    </div>
                    <p class="mt-5 text-sm text-zinc-600">Recomendacao: armazene respostas em cache quando possivel, use filtros para reduzir payloads e prefira consultas paginadas para telas grandes.</p>
                </section>
            </div>
        </section>
    </main>
</body>
</html>
