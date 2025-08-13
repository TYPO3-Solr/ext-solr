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

namespace ApacheSolrForTypo3\Solr\EventListener\PageIndexer;

use ApacheSolrForTypo3\Solr\Access\Rootline;
use ApacheSolrForTypo3\Solr\Event\Indexing\AfterContentAccessGroupsResolvedEvent;

final class AccessGroupsFilter
{
    public function __invoke(AfterContentAccessGroupsResolvedEvent $event): void
    {
        $validated = [];
        $pageAccessGroups = Rootline::getAccessRootlineByPageId($event->getItem()->getRecordUid(), $event->getItem()->getMountPointIdentifier())->getGroups();
        if (empty($pageAccessGroups)) {
            $pageAccessGroups[] = 0;
        }

        foreach ($event->getContentAccessGroups() as $contentGroup) {
            foreach ($pageAccessGroups as $pageGroup) {
                if ($this->isCombinationAllowed($pageGroup, $contentGroup)) {
                    $validated[] = $contentGroup;
                    break; // Once allowed for at least one page group, keep it
                }
            }
        }

        $event->setContentAccessGroups(array_values(array_unique($validated)));
    }

    protected function isCombinationAllowed(int $pageGroup, int $contentGroup): bool
    {
        // Page hidden at login (-1)
        if ($pageGroup === -1) {
            // Only visible to anonymous users
            return $contentGroup === -1 || $contentGroup === 0;
        }

        // Page visible only at login (-2)
        if ($pageGroup === -2) {
            // Visible to any logged-in user
            return $contentGroup === -2 || $contentGroup > 0;
        }

        // Page public (0)
        if ($pageGroup === 0) {
            // Any content restriction is fine — content decides visibility
            return true;
        }

        // Page restricted to specific FE group (>0)
        if ($pageGroup > 0) {
            // Allowed if content is:
            // - same group as page
            // - "any login" (-2) but only if it includes this group
            // (note: "-2" alone means ANY login, which is still fine for a logged-in user of this group)
            return $contentGroup === $pageGroup || $contentGroup === -2;
        }

        return false; // Should not happen
    }
}
