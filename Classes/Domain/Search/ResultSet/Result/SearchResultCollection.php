<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupCollection;
use ApacheSolrForTypo3\Solr\System\Data\AbstractCollection;

/**
 * The SearchResultCollection contains the SearchResult object and related objects. e.g groups.
 */
class SearchResultCollection extends AbstractCollection {

    /**
     * @var GroupCollection
     */
    protected $groups = null;

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
