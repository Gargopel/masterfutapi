<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Minha conta - MasterFut API</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-zinc-100 text-zinc-950">
    <main class="mx-auto min-h-screen max-w-6xl px-6 py-8">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-emerald-700">MasterFut API</p>
                <h1 class="text-3xl font-black">Ola, {{ $user->name }}</h1>
            </div>
            <form method="POST" action="/logout">
                @csrf
                <button class="rounded border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-zinc-50">Sair</button>
            </form>
        </header>

        <section class="mt-8 grid gap-4 lg:grid-cols-[.8fr_1.2fr]">
            <article class="rounded border border-zinc-200 bg-white p-5">
                <h2 class="font-bold">Conta</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-zinc-500">Email</dt><dd class="font-semibold">{{ $user->email }}</dd></div>
                    <div><dt class="text-zinc-500">Status</dt><dd class="font-semibold">Conta criada</dd></div>
                </dl>
            </article>

            <article class="rounded border border-zinc-200 bg-white p-5">
                <h2 class="font-bold">Endpoints publicos</h2>
                <div class="mt-4 grid gap-2 text-sm">
                    @foreach (['metadata', 'sports', 'countries', 'leagues', 'seasons', 'teams', 'matches', 'standings', 'stats/summary'] as $endpoint)
                        <a class="rounded bg-zinc-100 px-3 py-2 font-mono hover:bg-emerald-50" href="/api/v1/{{ $endpoint }}">/api/v1/{{ $endpoint }}</a>
                    @endforeach
                </div>
            </article>
        </section>
    </main>
</body>
</html>
