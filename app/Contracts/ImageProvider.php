<?php

namespace App\Contracts;

use App\Support\ImageResult;

interface ImageProvider
{
    /**
     * Search a royalty-free stock photo matching the query. Returns null
     * when nothing suitable is found rather than throwing, since a missing
     * featured image should never block publication.
     */
    public function search(string $query): ?ImageResult;
}
