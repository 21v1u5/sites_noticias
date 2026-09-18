<?php

namespace App\Services\Images;

use App\Contracts\ImageProvider;
use App\Support\ImageResult;
use Illuminate\Http\Client\Factory as HttpFactory;

class PexelsImageProvider implements ImageProvider
{
    public function __construct(private readonly HttpFactory $http) {}

    public function search(string $query): ?ImageResult
    {
        $apiKey = config('services.pexels.api_key');

        if (! $apiKey) {
            return null;
        }

        $response = $this->http
            ->timeout(15)
            ->withHeaders(['Authorization' => $apiKey])
            ->get('https://api.pexels.com/v1/search', [
                'query' => $query,
                'per_page' => 1,
                'orientation' => 'landscape',
            ]);

        if ($response->failed()) {
            return null;
        }

        $photo = $response->json('photos.0');

        if (! $photo) {
            return null;
        }

        return new ImageResult(
            url: $photo['src']['large'] ?? $photo['src']['original'] ?? '',
            creditName: $photo['photographer'] ?? 'Pexels',
            creditUrl: $photo['photographer_url'] ?? 'https://www.pexels.com',
        );
    }
}
