<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro - MasterFut API</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-zinc-950 text-white">
    <main class="grid min-h-screen place-items-center bg-[url('https://images.unsplash.com/photo-1517927033932-b3d18e61fb3a?auto=format&fit=crop&w=1600&q=80')] bg-cover bg-center px-6 py-10">
        <div class="absolute inset-0 bg-zinc-950/75"></div>
        <section class="relative w-full max-w-md rounded border border-white/15 bg-white/95 p-6 text-zinc-950 shadow-2xl">
            <a class="mb-6 inline-flex text-sm font-semibold text-zinc-500" href="/">Voltar</a>
            <h1 class="text-2xl font-black">Criar conta</h1>
            <p class="mt-2 text-sm text-zinc-600">Cadastre-se para acompanhar a API e preparar seu acesso aos dados.</p>
            <form class="mt-6 space-y-4" method="POST" action="/register">
                @csrf
                <label class="block text-sm font-medium">Nome
                    <input class="mt-1 w-full rounded border border-zinc-300 px-3 py-2" name="name" value="{{ old('name') }}" required>
                </label>
                <label class="block text-sm font-medium">Email
                    <input class="mt-1 w-full rounded border border-zinc-300 px-3 py-2" name="email" type="email" value="{{ old('email') }}" required>
                </label>
                <label class="block text-sm font-medium">Senha
                    <input class="mt-1 w-full rounded border border-zinc-300 px-3 py-2" name="password" type="password" required>
                </label>
                <label class="block text-sm font-medium">Confirmar senha
                    <input class="mt-1 w-full rounded border border-zinc-300 px-3 py-2" name="password_confirmation" type="password" required>
                </label>
                @if ($errors->any())
                    <p class="text-sm text-rose-600">{{ $errors->first() }}</p>
                @endif
                <button class="w-full rounded bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-700">Cadastrar</button>
            </form>
            <p class="mt-5 text-center text-sm text-zinc-600">Ja tem conta? <a class="font-semibold text-emerald-700" href="/login">Entrar</a></p>
        </section>
    </main>
</body>
</html>
