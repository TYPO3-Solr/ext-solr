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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupCollection;
use ApacheSolrForTypo3\Solr\System\Data\AbstractCollection;

/**
 * The SearchResultCollection contains the SearchResult object and related objects. e.g groups.
 */
class SearchResultCollection extends AbstractCollection
{
    /**
     * @var GroupCollection
     */
    protected GroupCollection $groups;

    /**
     * SearchResultCollection constructor.
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->groups = new GroupCollection();
    }

    /**
     * @return GroupCollection
     */
    public function getGroups(): GroupCollection
    {
        return $this->groups;
    }

    /**
     * @param GroupCollection $groups
     */
    public function setGroups(GroupCollection $groups)
    {
        $this->groups = $groups;
    }

    /**
     * @return bool
     */
    public function getHasGroups(): bool
    {
        return $this->groups->getCount() > 0;
    }
}
