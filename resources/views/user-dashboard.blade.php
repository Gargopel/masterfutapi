<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - MasterFut API</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-zinc-100 text-zinc-950">
    <div class="min-h-screen lg:grid lg:grid-cols-[280px_1fr]">
        <aside class="flex flex-col border-r border-zinc-200 bg-zinc-950 p-5 text-white lg:min-h-screen">
            <a href="/" class="flex items-center gap-3">
                <span class="grid h-10 w-10 place-items-center rounded bg-emerald-500 font-black text-zinc-950">MF</span>
                <span class="font-bold">MasterFut API</span>
            </a>

            <nav class="mt-8 grid gap-2 text-sm">
                <a class="rounded bg-white/10 px-3 py-2 font-semibold" href="/dashboard">Dashboard</a>
                <a class="rounded px-3 py-2 text-white/75 hover:bg-white/10 hover:text-white" href="/api-keys">API Keys</a>
                <a class="rounded px-3 py-2 text-white/75 hover:bg-white/10 hover:text-white" href="/profile">Perfil</a>
                <a class="rounded px-3 py-2 text-white/75 hover:bg-white/10 hover:text-white" href="/docs">Docs</a>
            </nav>

            <div class="mt-8 rounded border border-white/10 bg-white/5 p-4 text-sm text-white/75">
                <p class="font-semibold text-white">Conta</p>
                <p class="mt-1 truncate">{{ $user->email }}</p>
                <p class="mt-3 rounded bg-emerald-500/15 px-3 py-2 text-emerald-200">Status da conta em breve</p>
            </div>

            <form class="mt-auto pt-6" method="POST" action="/logout">
                @csrf
                <button class="w-full rounded border border-white/15 px-3 py-2 text-left text-sm font-semibold text-white/85 hover:bg-white/10">Sair</button>
            </form>
        </aside>

        <main class="p-5 lg:p-8">
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-emerald-700">Area do cliente</p>
                    <h1 class="mt-1 text-3xl font-black">Ola, {{ $user->name }}</h1>
                    <p class="mt-2 max-w-2xl text-zinc-600">Acompanhe a cobertura disponivel na MasterFut API e acesse rapidamente os endpoints principais.</p>
                </div>
                <a class="rounded bg-zinc-950 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800" href="/docs">Ver documentacao</a>
            </header>

            <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    'Ligas' => $stats['leagues'],
                    'Times' => $stats['teams'],
                    'Partidas' => $stats['matches'],
                    'Classificacoes' => $stats['standings'],
                ] as $label => $value)
                    <article class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-semibold text-zinc-500">{{ $label }}</p>
                        <p class="mt-3 text-4xl font-black">{{ number_format($value) }}</p>
                    </article>
                @endforeach
            </section>

            <section class="mt-6 grid gap-4 xl:grid-cols-[1.2fr_.8fr]">
                <article class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold">Endpoint base</h2>
                            <p class="text-sm text-zinc-500">Use esta URL como raiz para todas as chamadas da API v1.</p>
                        </div>
                        <a class="rounded border border-zinc-300 px-3 py-2 text-sm font-semibold hover:bg-zinc-50" href="/api-keys">Gerar chave</a>
                    </div>
                    <code class="mt-4 block overflow-x-auto rounded bg-zinc-950 p-4 text-sm text-emerald-200">{{ url('/api/v1') }}</code>
                </article>

                <article class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold">Resumo da cobertura</h2>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded bg-zinc-100 p-3"><dt class="text-zinc-500">Esportes</dt><dd class="font-bold">{{ number_format($stats['sports']) }}</dd></div>
                        <div class="rounded bg-zinc-100 p-3"><dt class="text-zinc-500">Paises</dt><dd class="font-bold">{{ number_format($stats['countries']) }}</dd></div>
                        <div class="rounded bg-zinc-100 p-3"><dt class="text-zinc-500">Temporadas</dt><dd class="font-bold">{{ number_format($stats['seasons']) }}</dd></div>
                        <div class="rounded bg-zinc-100 p-3"><dt class="text-zinc-500">Versao</dt><dd class="font-bold">v1</dd></div>
                    </dl>
                </article>
            </section>

            <section class="mt-6 rounded border border-zinc-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold">Endpoints principais</h2>
                        <p class="text-sm text-zinc-500">Links rapidos para explorar a API.</p>
                    </div>
                    <a class="rounded border border-zinc-300 px-3 py-2 text-sm font-semibold hover:bg-zinc-50" href="/docs">Ver exemplos</a>
                </div>
                <div class="mt-4 grid gap-2 text-sm md:grid-cols-2 xl:grid-cols-3">
                    @foreach (['metadata', 'sports', 'countries', 'leagues', 'seasons', 'teams', 'matches', 'standings', 'stats/summary'] as $endpoint)
                        <code class="rounded bg-zinc-100 px-3 py-2 font-mono">/api/v1/{{ $endpoint }}</code>
                    @endforeach
                </div>
            </section>
        </main>
    </div>
</body>
</html>
