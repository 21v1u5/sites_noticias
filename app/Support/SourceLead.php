<?php

namespace App\Support;

/**
 * A single raw fact/pauta extracted from a NewsSource, before any AI writing
 * happens. `facts` carries the structured data point(s) (e.g. an IPCA value
 * and reference period) that the GenerateArticleJob hands to the AI as the
 * material to write an original article from - never a body of text to be
 * reworded.
 */
final class SourceLead
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $title,
        public readonly string $summary,
        public readonly ?string $referenceUrl = null,
        public readonly ?string $referenceTitle = null,
        public readonly array $facts = [],
    ) {}
}
