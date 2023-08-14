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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;

/**
 * Class GroupItem
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class GroupItem extends SearchResultSet
{
    protected string $groupValue = '';

    protected int $allResultCount = 0;

    protected int $start = 0;

    protected float $maximumScore = 0.0;

    protected SearchResultCollection $searchResults;

    protected Group $group;

    public function __construct(
        Group $group,
        string $groupValue,
        int $numFound,
        int $start,
        float $maxScore,
        SearchRequest $usedSearchRequest,
    ) {
        parent::__construct();
        $this->group = $group;
        $this->groupValue = $groupValue;
        $this->allResultCount = $numFound;
        $this->start = $start;
        $this->maximumScore = $maxScore;
        $this->searchResults = new SearchResultCollection();
        $this->usedSearchRequest = $usedSearchRequest;
    }

    /**
     * Get groupValue
     */
    public function getGroupValue(): string
    {
        return $this->groupValue;
    }

    /**
     * Get start
     */
    public function getStart(): int
    {
        return $this->start;
    }

    /**
     * Get Group
     */
    public function getGroup(): Group
    {
        return $this->group;
    }
}
