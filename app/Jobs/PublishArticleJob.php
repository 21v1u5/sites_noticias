<?php

namespace App\Jobs;

use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Final step of the pipeline. Dispatched with a delay() matching the
 * article's scheduled_for slot (see FetchArticleImageJob::nextPublishSlot),
 * so it naturally fires at the spaced-out time instead of needing its own
 * poll/cron. Flips the article live and busts the home page cache so the
 * next visitor sees it without hitting the database.
 */
class PublishArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly Article $article) {}

    public function handle(): void
    {
        $this->article->refresh();

        if ($this->article->status === Article::STATUS_PUBLISHED) {
            return;
        }

        $this->article->forceFill([
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now(),
        ])->save();

        $site = $this->article->site;

        Cache::forget("site:{$site->id}:home");
        Cache::forget("site:{$site->id}:article:{$this->article->slug}");
    }
}
