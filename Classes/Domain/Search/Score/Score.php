<?php

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

namespace ApacheSolrForTypo3\Solr\Domain\Search\Score;

/**
 * Holds the data of an analyzed score.
 */
class Score
{
    public function __construct(
        protected float $boost = 0.0,
        protected string $fieldName = '',
        protected float $score = 0.0,
        protected string $searchTerm = '',
    ) {}

    public function getBoost(): float
    {
        return $this->boost;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }
}
