@extends('layouts.app')

@section('title', $article->title.' - '.current_site()->name)
@section('description', $article->excerpt)

@section('content')
    <article>
        <h1 class="text-2xl font-bold">{{ $article->title }}</h1>

        <p class="mt-2 text-xs text-neutral-400">
            Publicado em {{ $article->published_at?->translatedFormat('d \d\e F \d\e Y, H:i') }}
        </p>

        @if($article->featured_image_url)
            <figure class="mt-4">
                <img
                    src="{{ $article->featured_image_url }}"
                    alt="{{ $article->title }}"
                    class="aspect-video w-full rounded-lg object-cover"
                >
                @if($article->featured_image_credit_name)
                    <figcaption class="mt-1 text-xs text-neutral-400">
                        Foto:
                        @if($article->featured_image_credit_url)
                            <a href="{{ $article->featured_image_credit_url }}" rel="nofollow noopener" target="_blank">{{ $article->featured_image_credit_name }}</a>
                        @else
                            {{ $article->featured_image_credit_name }}
                        @endif
                    </figcaption>
                @endif
            </figure>
        @endif

        {{-- $contentWithAd already has the in-article ad slot spliced in
             after the 2nd paragraph by App\Support\ArticleAdInjector. --}}
        <div class="article-content mt-6">
            {!! $contentWithAd !!}
        </div>

        @if($article->source_reference_url)
            <p class="mt-8 border-t border-neutral-200 pt-4 text-sm text-neutral-500">
                Fonte:
                <a href="{{ $article->source_reference_url }}" rel="nofollow noopener" target="_blank">
                    {{ $article->source_reference_title ?? $article->source_reference_url }}
                </a>
            </p>
        @endif
    </article>
@endsection
