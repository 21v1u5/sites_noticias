@extends('layouts.app')

@section('content')
    <h1 class="text-2xl font-bold">{{ current_site()->name }}</h1>

    <div class="mt-6 space-y-8">
        @forelse($articles as $article)
            <article class="border-b border-neutral-200 pb-8">
                @if($article->featured_image_url)
                    <a href="{{ route('articles.show', $article->slug) }}">
                        <img
                            src="{{ $article->featured_image_url }}"
                            alt="{{ $article->title }}"
                            loading="lazy"
                            class="mb-3 aspect-video w-full rounded-lg object-cover"
                        >
                    </a>
                @endif

                <h2 class="text-lg font-semibold">
                    <a href="{{ route('articles.show', $article->slug) }}">{{ $article->title }}</a>
                </h2>

                @if($article->excerpt)
                    <p class="mt-2 text-neutral-600">{{ $article->excerpt }}</p>
                @endif

                <p class="mt-2 text-xs text-neutral-400">
                    Publicado em {{ $article->published_at?->translatedFormat('d \d\e F \d\e Y, H:i') }}
                </p>
            </article>
        @empty
            <p class="text-neutral-500">Nenhum artigo publicado ainda.</p>
        @endforelse
    </div>

    <div class="mt-8">
        {{ $articles->links() }}
    </div>
@endsection
