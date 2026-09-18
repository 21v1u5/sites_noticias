<?php

namespace App\Jobs;

use App\Models\NewsSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ingestion step of the pipeline: fetches raw facts from one NewsSource and
 * fans out a GenerateArticleJob per (site, lead) pair that hasn't been
 * turned into an article yet. Runs hourly per active source via the
 * scheduler (routes/console.php), on the Redis queue so it never blocks
 * the web/PHP-FPM process.
 */
class FetchNewsSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    public function __construct(public readonly NewsSource $newsSource) {}

    public function handle(): void
    {
        if (! $this->newsSource->is_active) {
            return;
        }

        try {
            $leads = app($this->newsSource->connector)->fetch($this->newsSource);
        } catch (Throwable $e) {
            Log::warning("[ingestao] falha na fonte [{$this->newsSource->key}]: {$e->getMessage()}");

            throw $e;
        }

        if ($leads->isEmpty()) {
            return;
        }

        $sites = $this->newsSource->sites()->where('is_active', true)->get();

        foreach ($sites as $site) {
            foreach ($leads as $lead) {
                $alreadyIngested = $site->articles()
                    ->where('external_lead_id', $lead->externalId)
                    ->exists();

                if ($alreadyIngested) {
                    continue;
                }

                GenerateArticleJob::dispatch($site, $this->newsSource, $lead);
            }
        }

        if ($sites->isNotEmpty()) {
            $this->newsSource->sites()->updateExistingPivot(
                $sites->pluck('id')->all(),
                ['last_fetched_at' => now()],
            );
        }
    }
}
