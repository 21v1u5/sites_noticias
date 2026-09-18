<?php

namespace App\Jobs;

use App\Contracts\ImageProvider;
use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Media step of the pipeline: finds a royalty-free featured image, then
 * schedules the article into the site's publish queue with human-like
 * spacing (never dumps a batch of articles at once - see nextPublishSlot()).
 */
class FetchArticleImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 30;

    private const STOPWORDS = [
        'a', 'o', 'as', 'os', 'de', 'da', 'do', 'das', 'dos', 'em', 'no', 'na', 'nos', 'nas',
        'para', 'por', 'com', 'sem', 'sobre', 'entre', 'e', 'ou', 'que', 'um', 'uma', 'uns', 'umas',
        'ao', 'aos', 'à', 'às', 'é', 'foi', 'ser', 'sao', 'são', 'como', 'mais', 'menos', 'ate', 'até',
    ];

    public function __construct(public readonly Article $article) {}

    public function handle(ImageProvider $imageProvider): void
    {
        $image = $imageProvider->search($this->searchQuery());

        $this->article->forceFill([
            'featured_image_url' => $image?->url,
            'featured_image_credit_name' => $image?->creditName,
            'featured_image_credit_url' => $image?->creditUrl,
            'status' => Article::STATUS_SCHEDULED,
            'scheduled_for' => $this->nextPublishSlot(),
        ])->save();

        PublishArticleJob::dispatch($this->article)->delay($this->article->scheduled_for);
    }

    /**
     * Cheap local keyword extraction from the generated title (strip
     * stopwords/punctuation, keep the most distinctive words) instead of a
     * second DeepSeek call - image search only needs a rough topic match,
     * not perfect keywords, so this saves a full LLM round trip per article.
     */
    private function searchQuery(): string
    {
        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($this->article->title)))
            ->filter(fn (string $word) => mb_strlen($word) > 2 && ! in_array($word, self::STOPWORDS, true))
            ->unique()
            ->take(4)
            ->implode(' ');

        return $words !== '' ? $words : $this->article->site->niche;
    }

    private function nextPublishSlot(): Carbon
    {
        $site = $this->article->site;

        $lastScheduled = $site->articles()
            ->whereIn('status', [Article::STATUS_SCHEDULED, Article::STATUS_PUBLISHED])
            ->where('id', '!=', $this->article->id)
            ->max('scheduled_for');

        $earliestSlot = $lastScheduled
            ? Carbon::parse($lastScheduled)->addMinutes($site->publish_spacing_minutes)
            : now();

        // Small random jitter so publish times don't look machine-perfect.
        return $earliestSlot->max(now())->addMinutes(random_int(0, 8));
    }
}
