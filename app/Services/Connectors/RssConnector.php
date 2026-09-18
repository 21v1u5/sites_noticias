<?php

namespace App\Services\Connectors;

use App\Contracts\NewsSourceConnector;
use App\Models\NewsSource;
use App\Support\SourceLead;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SimpleXMLElement;

/**
 * Generic RSS/Atom feed reader. Intended for official, openly-licensed
 * feeds (ex: Agencia Brasil under Creative Commons, government press
 * releases) - it only extracts title/summary/link as a lead for the AI to
 * write an original piece from, it never ingests full article bodies.
 */
class RssConnector implements NewsSourceConnector
{
    public function __construct(private readonly HttpFactory $http) {}

    public function fetch(NewsSource $source): Collection
    {
        $response = $this->http->timeout(20)->retry(2, 500)->get($source->endpoint);
        $response->throw();

        $xml = @simplexml_load_string($response->body(), SimpleXMLElement::class, LIBXML_NOCDATA);

        if ($xml === false) {
            return collect();
        }

        $items = $xml->channel->item ?? $xml->entry ?? [];

        return collect($items)->map(function ($item) use ($source) {
            $link = (string) ($item->link['href'] ?? $item->link ?? '');
            $title = trim((string) ($item->title ?? ''));
            $description = trim(strip_tags((string) ($item->description ?? $item->summary ?? '')));
            $guid = trim((string) ($item->guid ?? $link ?: $title));

            return new SourceLead(
                externalId: "{$source->key}:".md5($guid),
                title: $title,
                summary: Str::limit($description, 600),
                referenceUrl: $link ?: null,
                referenceTitle: $source->name,
                facts: ['descricao_original' => $description],
            );
        })->filter(fn (SourceLead $lead) => $lead->title !== '')->values();
    }
}
