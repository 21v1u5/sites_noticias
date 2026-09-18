<?php

namespace App\Services\Images;

use App\Contracts\ImageProvider;
use App\Support\ImageResult;
use Illuminate\Http\Client\Factory as HttpFactory;

class UnsplashImageProvider implements ImageProvider
{
    public function __construct(private readonly HttpFactory $http) {}

    public function search(string $query): ?ImageResult
    {
        $accessKey = config('services.unsplash.access_key');

        if (! $accessKey) {
            return null;
        }

        $response = $this->http
            ->timeout(15)
            ->withHeaders(['Authorization' => "Client-ID {$accessKey}"])
            ->get('https://api.unsplash.com/search/photos', [
                'query' => $query,
                'per_page' => 1,
                'orientation' => 'landscape',
                'content_filter' => 'high',
            ]);

        if ($response->failed()) {
            return null;
        }

        $photo = $response->json('results.0');

        if (! $photo) {
            return null;
        }

        return new ImageResult(
            url: $photo['urls']['regular'] ?? $photo['urls']['full'] ?? '',
            creditName: $photo['user']['name'] ?? 'Unsplash',
            creditUrl: ($photo['user']['links']['html'] ?? 'https://unsplash.com').'?utm_source=referral&utm_medium=referral',
        );
    }
}
