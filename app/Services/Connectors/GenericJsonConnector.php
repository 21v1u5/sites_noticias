<?php

namespace App\Services\Connectors;

use App\Contracts\NewsSourceConnector;
use App\Models\NewsSource;
use App\Support\SourceLead;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;

/**
 * Configuration-driven connector for simple public JSON APIs that don't
 * warrant a dedicated class (World Bank, Eurostat, NASA, NOAA, WHO, ...).
 * Config per NewsSource:
 *   - query: extra query-string params sent with the request
 *   - items_path: dot path to the array of items in the response body
 *     (omit if the response root IS the array)
 *   - id_field / title_field / summary_field: dot paths within each item
 *   - reference_url / reference_title: attribution shown on the article
 */
class GenericJsonConnector implements NewsSourceConnector
{
    public function __construct(private readonly HttpFactory $http) {}

    public function fetch(NewsSource $source): Collection
    {
        $config = $source->config ?? [];

        $response = $this->http->timeout(20)->retry(2, 500)->get($source->endpoint, $config['query'] ?? []);
        $response->throw();

        $body = $response->json();
        $items = isset($config['items_path']) ? data_get($body, $config['items_path']) : $body;

        if (! is_array($items)) {
            return collect();
        }

        return collect($items)
            ->map(function ($item) use ($source, $config) {
                if (! is_array($item)) {
                    return null;
                }

                $id = (string) data_get($item, $config['id_field'] ?? 'id');
                $title = trim((string) data_get($item, $config['title_field'] ?? 'title'));
                $summary = trim((string) data_get($item, $config['summary_field'] ?? 'summary'));

                if ($title === '') {
                    return null;
                }

                return new SourceLead(
                    externalId: "{$source->key}:".($id !== '' ? $id : md5($title)),
                    title: $title,
                    summary: $summary,
                    referenceUrl: $config['reference_url'] ?? $source->endpoint,
                    referenceTitle: $config['reference_title'] ?? $source->name,
                    facts: $item,
                );
            })
            ->filter()
            ->values();
    }
}
