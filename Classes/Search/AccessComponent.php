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

namespace ApacheSolrForTypo3\Solr\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Event\Search\AfterSearchQueryHasBeenPreparedEvent;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;

/**
 * Access search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class AccessComponent
{
    public function __construct(
        protected readonly QueryBuilder $queryBuilder
    ) {
    }

    /**
     * Initializes the search component.
     *
     * @throws AspectNotFoundException
     */
    public function __invoke(AfterSearchQueryHasBeenPreparedEvent $event): void
    {
        $query = $this->queryBuilder
            ->startFrom($event->getQuery())
            ->useSiteHashFromTypoScript($GLOBALS['TSFE']->id)
            ->useUserAccessGroups(Util::getFrontendUserGroups())
            ->getQuery();
        $event->setQuery($query);
    }
}
