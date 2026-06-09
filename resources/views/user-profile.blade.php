<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Perfil - MasterFut API</title>
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
                <a class="rounded bg-white/10 px-3 py-2 font-semibold" href="/profile">Perfil</a>
                <a class="rounded px-3 py-2 text-white/75 hover:bg-white/10 hover:text-white" href="/docs">Docs</a>
                <a class="rounded px-3 py-2 text-white/75 hover:bg-white/10 hover:text-white" href="/api/v1/metadata">Metadata</a>
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
            <header>
                <p class="text-sm font-semibold text-emerald-700">Configuracoes</p>
                <h1 class="mt-1 text-3xl font-black">Perfil</h1>
                <p class="mt-2 max-w-2xl text-zinc-600">Gerencie os dados da sua conta MasterFut API.</p>
            </header>

            <section class="mt-8 grid gap-4 xl:grid-cols-[.8fr_1.2fr]">
                <article class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold">Dados da conta</h2>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div>
                            <dt class="text-zinc-500">Nome</dt>
                            <dd class="mt-1 font-semibold">{{ $user->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Email</dt>
                            <dd class="mt-1 font-semibold">{{ $user->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500">Status</dt>
                            <dd class="mt-1 font-semibold">Conta criada</dd>
                        </div>
                    </dl>
                </article>

                <article class="rounded border border-zinc-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold">Alterar senha</h2>
                    <p class="mt-1 text-sm text-zinc-500">Use uma senha forte com pelo menos 8 caracteres.</p>

                    @if (session('status'))
                        <div class="mt-4 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{{ session('status') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="mt-4 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form class="mt-5 grid gap-4" method="POST" action="/profile/password">
                        @csrf
                        <label class="grid gap-2 text-sm font-semibold">
                            Senha atual
                            <input class="rounded border border-zinc-300 px-3 py-2 font-normal" type="password" name="current_password" required autocomplete="current-password">
                        </label>
                        <label class="grid gap-2 text-sm font-semibold">
                            Nova senha
                            <input class="rounded border border-zinc-300 px-3 py-2 font-normal" type="password" name="password" required autocomplete="new-password">
                        </label>
                        <label class="grid gap-2 text-sm font-semibold">
                            Confirmar nova senha
                            <input class="rounded border border-zinc-300 px-3 py-2 font-normal" type="password" name="password_confirmation" required autocomplete="new-password">
                        </label>
                        <button class="w-fit rounded bg-zinc-950 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800">Salvar senha</button>
                    </form>
                </article>
            </section>
        </main>
    </div>
</body>
</html>
