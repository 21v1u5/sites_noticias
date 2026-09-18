<?php

use App\Jobs\FetchNewsSourceJob;
use App\Models\NewsSource;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ingestion tick: fans out one FetchNewsSourceJob per active source onto the
// Redis queue, where the dedicated worker container processes it. Never runs
// inline here - this only dispatches, so a slow/failing source never blocks
// the scheduler itself. Frequency is configurable via
// NEWS_INGESTION_FREQUENCY_MINUTES (default 60 = hourly).
Schedule::call(function () {
    NewsSource::query()
        ->where('is_active', true)
        ->each(fn (NewsSource $source) => FetchNewsSourceJob::dispatch($source));
})
    ->cron('*/'.max(1, (int) config('services.news_ingestion.frequency_minutes', 60)).' * * * *')
    ->name('ingest-news-sources')
    ->withoutOverlapping()
    ->onOneServer();
