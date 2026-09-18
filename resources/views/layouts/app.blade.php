<!doctype html>
<html lang="{{ str_replace('_', '-', current_site()->locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', current_site()->name)</title>
    <meta name="description" content="@yield('description', current_site()->tagline)">

    @if(current_site()->adsense_client_id)
        <script async
            src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ current_site()->adsense_client_id }}"
            crossorigin="anonymous"></script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-neutral-900 antialiased">
    <a href="#conteudo" class="sr-only focus:not-sr-only">Pular para o conteudo</a>

    <header class="border-b border-neutral-200">
        <div class="mx-auto max-w-5xl px-4 py-4">
            <a href="{{ route('home') }}" class="text-xl font-semibold">{{ current_site()->name }}</a>
            @if(current_site()->tagline)
                <p class="text-sm text-neutral-500">{{ current_site()->tagline }}</p>
            @endif
        </div>
    </header>

    <x-adsense-slot position="header" />

    <div class="mx-auto grid max-w-5xl grid-cols-1 gap-8 px-4 py-8 lg:grid-cols-[2fr_1fr]">
        <main id="conteudo">
            @yield('content')
        </main>

        <aside>
            <x-adsense-slot position="sidebar" />
        </aside>
    </div>

    <footer class="border-t border-neutral-200">
        <div class="mx-auto max-w-5xl px-4 py-6">
            <x-adsense-slot position="footer" />
            <p class="mt-4 text-sm text-neutral-500">&copy; {{ now()->year }} {{ current_site()->name }}</p>
        </div>
    </footer>
</body>
</html>
