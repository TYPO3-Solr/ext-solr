<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping;

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

use ApacheSolrForTypo3\Solr\System\Data\AbstractCollection;

/**
 * The Group contains the Group objects.
 */
class GroupCollection extends AbstractCollection {

    /**
     * @param string $name
     * @return Group|null
     */
    public function getByName($name)
    {
        foreach ($this->data as $group) {
            /** @var $group Group */
            if ($group->getGroupName() === $name) {
                return $group;
            }
        }

        return null;
    }

    /**
     * @param string $name
     * @return boolean
     */
    public function getHasWithName($name): bool
    {
        foreach ($this->data as $group) {
            /** @var $group Group */
            if ($group->getGroupName() === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function getGroupNames(): array
    {
        $names = [];
        foreach ($this->data as $group) {
            /** @var $group Group */
            $names[] = $group->getGroupName();
        }

        return $names;
    }

    /**
     * @param Group $group
     */
    public function add(Group $group)
    {
        $this->data[] = $group;
    }
}
