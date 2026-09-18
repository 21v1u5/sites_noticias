<?php

namespace App\Contracts;

use App\Models\NewsSource;
use App\Support\SourceLead;
use Illuminate\Support\Collection;

interface NewsSourceConnector
{
    /**
     * Fetch the latest raw facts/leads from this source. Implementations
     * must be safe to call repeatedly (idempotent reads) - deduplication
     * against already-ingested leads happens downstream, keyed by each
     * SourceLead::$externalId.
     *
     * @return Collection<int, SourceLead>
     */
    public function fetch(NewsSource $source): Collection;
}
