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

/**
 * A group is identified by a groupName and can contain multiple groupItems (that reference the search results).
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class Group
{
    protected string $groupName = '';

    protected int $resultsPerPage = 10;

    protected GroupItemCollection $groupItems;

    public function __construct(string $groupName, int $resultsPerPage = 10)
    {
        $this->groupName = $groupName;
        $this->groupItems = new GroupItemCollection();
        $this->resultsPerPage = $resultsPerPage;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function setGroupName(string $groupName): void
    {
        $this->groupName = $groupName;
    }

    public function getGroupItems(): GroupItemCollection
    {
        return $this->groupItems;
    }

    public function setGroupItems(GroupItemCollection $groupItems): void
    {
        $this->groupItems = $groupItems;
    }

    public function addGroupItem(GroupItem $groupItem): void
    {
        $this->groupItems[] = $groupItem;
    }

    public function getResultsPerPage(): int
    {
        return $this->resultsPerPage;
    }

    public function setResultsPerPage(int $resultsPerPage): void
    {
        $this->resultsPerPage = $resultsPerPage;
    }
}
