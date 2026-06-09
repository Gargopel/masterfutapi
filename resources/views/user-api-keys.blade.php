<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Keys - MasterFut API</title>
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
                <a class="rounded px-3 py-2 text-white/75 hover:bg-white/10 hover:text-white" href="/dashboard">Dashboard</a>
                <a class="rounded bg-white/10 px-3 py-2 font-semibold" href="/api-keys">API Keys</a>
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
                    <p class="text-sm font-semibold text-emerald-700">Autenticacao</p>
                    <h1 class="mt-1 text-3xl font-black">API Keys</h1>
                    <p class="mt-2 max-w-2xl text-zinc-600">Crie chaves para autenticar suas requisicoes na MasterFut API. O plano free permite 3 chaves ativas e 10 requisicoes por minuto.</p>
                </div>
                <a class="rounded border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold hover:bg-zinc-50" href="/docs">Ver docs</a>
            </header>

            @if ($plainTextToken)
                <section class="mt-8 rounded border border-emerald-200 bg-emerald-50 p-5">
                    <p class="font-bold text-emerald-900">Chave criada com sucesso</p>
                    <p class="mt-1 text-sm text-emerald-800">Copie agora. Por seguranca, nao mostraremos este token novamente.</p>
                    <code class="mt-4 block overflow-x-auto rounded bg-zinc-950 p-4 text-sm text-emerald-200">{{ $plainTextToken }}</code>
                </section>
            @endif

            @if (session('status'))
                <div class="mt-8 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mt-8 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <section class="mt-8 grid gap-4 xl:grid-cols-[.75fr_1.25fr]">
                <article class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold">Nova chave</h2>
                    <form class="mt-5 grid gap-4" method="POST" action="/api-keys">
                        @csrf
                        <label class="grid gap-2 text-sm font-semibold">
                            Nome da chave
                            <input class="rounded border border-zinc-300 px-3 py-2 font-normal" name="name" value="{{ old('name', 'Producao') }}" maxlength="120" required>
                        </label>
                        <button class="rounded bg-zinc-950 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800">Gerar API key</button>
                    </form>

                    <div class="mt-6 rounded bg-zinc-100 p-4 text-sm text-zinc-600">
                        <p class="font-semibold text-zinc-950">Como autenticar</p>
                        <code class="mt-3 block overflow-x-auto rounded bg-white p-3">Authorization: Bearer sua_chave</code>
                        <code class="mt-2 block overflow-x-auto rounded bg-white p-3">X-API-Key: sua_chave</code>
                        <p class="mt-3 text-xs">Chaves revogadas nao contam no limite de 3 chaves ativas.</p>
                    </div>
                </article>

                <article class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold">Chaves existentes</h2>
                    <div class="mt-5 grid gap-3">
                        @forelse ($tokens as $token)
                            <div class="rounded border border-zinc-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-bold">{{ $token->name }}</p>
                                        <p class="mt-1 font-mono text-sm text-zinc-500">{{ $token->token_prefix }}...</p>
                                        <p class="mt-2 text-xs text-zinc-500">Criada em {{ $token->created_at->format('d/m/Y H:i') }} · Ultimo uso {{ $token->last_used_at?->format('d/m/Y H:i') ?? 'nunca' }}</p>
                                    </div>
                                    @if ($token->revoked_at)
                                        <span class="rounded bg-zinc-100 px-3 py-1 text-xs font-bold text-zinc-600">Revogada</span>
                                    @else
                                        <form method="POST" action="/api-keys/{{ $token->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="rounded border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Revogar</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="rounded bg-zinc-100 p-4 text-sm text-zinc-600">Nenhuma chave criada ainda.</p>
                        @endforelse
                    </div>
                </article>
            </section>
        </main>
    </div>
</body>
</html>
