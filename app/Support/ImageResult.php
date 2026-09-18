<?php

namespace App\Support;

final class ImageResult
{
    public function __construct(
        public readonly string $url,
        public readonly string $creditName,
        public readonly string $creditUrl,
    ) {}
}
