<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $settings['brand_name'] }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-zinc-950 text-white">
    <main>
        <section class="relative min-h-[92vh] overflow-hidden">
            <img class="absolute inset-0 h-full w-full object-cover" src="{{ $settings['hero_image_url'] }}" alt="Sports stadium">
            <div class="absolute inset-0 bg-[linear-gradient(90deg,rgba(2,6,23,.94),rgba(2,6,23,.74),rgba(2,6,23,.25))]"></div>
            <div class="relative mx-auto flex min-h-[92vh] max-w-7xl flex-col px-6 py-6 lg:px-8">
                <header class="flex items-center justify-between">
                    <a href="/" class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded bg-white text-zinc-950 font-black">MF</span>
                        <span class="text-lg font-semibold">{{ $settings['brand_name'] }}</span>
                    </a>
                    <nav class="flex items-center gap-3 text-sm">
                        <a class="hidden rounded px-3 py-2 text-white/80 hover:text-white sm:inline-flex" href="/api/v1/metadata">API</a>
                        <a class="rounded px-3 py-2 text-white/80 hover:text-white" href="/login">Login</a>
                        <a class="rounded bg-white px-4 py-2 font-semibold text-zinc-950 hover:bg-emerald-100" href="/register">Criar conta</a>
                    </nav>
                </header>

                <div class="grid flex-1 items-center gap-10 py-12 lg:grid-cols-[1.05fr_.95fr]">
                    <div class="max-w-3xl">
                        <p class="mb-5 inline-flex rounded-full border border-white/20 bg-white/10 px-4 py-2 text-sm text-white/85 backdrop-blur">{{ $settings['nav_badge'] }}</p>
                        <h1 class="max-w-4xl text-5xl font-black leading-tight tracking-normal text-white md:text-7xl">{{ $settings['hero_title'] }}</h1>
                        <p class="mt-6 max-w-2xl text-lg leading-8 text-zinc-200">{{ $settings['hero_subtitle'] }}</p>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a class="rounded px-5 py-3 font-semibold text-white shadow-lg" style="background-color: {{ $settings['accent_color'] }}" href="{{ $settings['primary_cta_url'] }}">{{ $settings['primary_cta_label'] }}</a>
                            <a class="rounded border border-white/30 px-5 py-3 font-semibold text-white hover:bg-white/10" href="{{ $settings['secondary_cta_url'] }}">{{ $settings['secondary_cta_label'] }}</a>
                        </div>
                    </div>

                    <div class="grid gap-3 rounded border border-white/15 bg-zinc-950/45 p-4 backdrop-blur-md">
                        <div class="grid grid-cols-2 gap-3">
                            @foreach ($stats as $label => $value)
                                <div class="rounded border border-white/10 bg-white/10 p-4">
                                    <p class="text-sm capitalize text-zinc-300">{{ str_replace('_', ' ', $label) }}</p>
                                    <p class="mt-2 text-3xl font-black">{{ number_format($value) }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="rounded bg-white p-4 text-zinc-950">
                            <p class="text-sm font-semibold text-zinc-500">Endpoint base</p>
                            <code class="mt-2 block overflow-x-auto rounded bg-zinc-100 p-3 text-sm">{{ url('/api/v1') }}</code>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="bg-white py-16 text-zinc-950">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mb-10 flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-widest" style="color: {{ $settings['accent_color'] }}">Data products</p>
                        <h2 class="mt-2 text-3xl font-black md:text-5xl">Feita para operar dados esportivos de verdade.</h2>
                    </div>
                    <a class="rounded border border-zinc-300 px-4 py-2 text-sm font-semibold hover:bg-zinc-100" href="/admin">Admin</a>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ($settings['features'] as $feature)
                        <article class="rounded border border-zinc-200 p-6">
                            <div class="mb-5 h-1 w-14 rounded" style="background-color: {{ $settings['accent_color'] }}"></div>
                            <h3 class="text-xl font-bold">{{ $feature['title'] }}</h3>
                            <p class="mt-3 leading-7 text-zinc-600">{{ $feature['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    </main>
</body>
</html>
