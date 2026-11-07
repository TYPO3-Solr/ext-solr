<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\Domain\Search\Statistics\Phrases;

/**
 * A row of the search statistics: a single search term, or a theme whose
 * variants are the terms it groups, the most searched first.
 */
final readonly class SearchPhrase
{
    /**
     * @param list<SearchPhrase> $variants
     */
    public function __construct(
        public string $label,
        public int $languageId,
        public int $count,
        public float $averageHits,
        public float $percent,
        public array $variants = [],
    ) {}

    public function hasVariants(): bool
    {
        return $this->variants !== [];
    }
}
