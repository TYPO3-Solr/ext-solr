<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

/**
 * A group is identified by a groupName and can contain multiple groupItems (that reference the search results).
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping
 */
class Group
{
    /**
     * @var string
     */
    protected $groupName = '';

    /**
     * @var GroupItemCollection
     */
    protected $groupItems = null;

    /**
     * Group constructor.
     * @param string $groupName
     */
    public function __construct(string $groupName)
    {
        $this->groupName = $groupName;
        $this->groupItems = new GroupItemCollection();
    }

    /**
     * @return string
     */
    public function getGroupName(): string
    {
        return $this->groupName;
    }

    /**
     * @param string $groupName
     */
    public function setGroupName(string $groupName)
    {
        $this->groupName = $groupName;
    }

    /**
     * @return GroupItemCollection
     */
    public function getGroupItems(): GroupItemCollection
    {
        return $this->groupItems;
    }

    /**
     * @param GroupItemCollection $groupItems
     */
    public function setGroupItems(GroupItemCollection $groupItems)
    {
        $this->groupItems = $groupItems;
    }

    /**
     * @param GroupItem $groupItem
     */
    public function addGroupItem(GroupItem $groupItem)
    {
        $this->groupItems[] = $groupItem;
    }
}
