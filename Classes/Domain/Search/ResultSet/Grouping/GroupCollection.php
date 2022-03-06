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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping;

use ApacheSolrForTypo3\Solr\System\Data\AbstractCollection;

/**
 * The Group contains the Group objects.
 */
class GroupCollection extends AbstractCollection
{
    /**
     * @param string $name
     * @return Group|null
     */
    public function getByName(string $name): ?Group
    {
        foreach ($this->data as $group) {
            /* @var Group $group */
            if ($group->getGroupName() === $name) {
                return $group;
            }
        }
        return null;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function getHasWithName(string $name): bool
    {
        foreach ($this->data as $group) {
            /* @var Group $group */
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
            /* @var Group $group */
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
