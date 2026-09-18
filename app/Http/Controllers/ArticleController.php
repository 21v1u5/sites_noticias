<?php

namespace App\Http\Controllers;

use App\Support\ArticleAdInjector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ArticleController extends Controller
{
    public function index(Request $request)
    {
        $site = current_site();
        $page = (int) $request->query('page', 1);

        $articles = Cache::remember(
            "site:{$site->id}:home:page:{$page}",
            now()->addMinutes(10),
            fn () => $site->articles()->published()->latest('published_at')->paginate(12),
        );

        return view('home', compact('articles'));
    }

    public function show(string $slug)
    {
        $site = current_site();

        $article = Cache::remember(
            "site:{$site->id}:article:{$slug}",
            now()->addMinutes(10),
            fn () => $site->articles()->published()->where('slug', $slug)->firstOrFail(),
        );

        $contentWithAd = ArticleAdInjector::insert(
            $article->content ?? '',
            render_adsense_slot('in_article'),
        );

        return view('articles.show', [
            'article' => $article,
            'contentWithAd' => $contentWithAd,
        ]);
    }
}
