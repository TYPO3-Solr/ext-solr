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

namespace ApacheSolrForTypo3\Solr\Query\Modifier;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;

/**
 * Enables tracking of detailed statistics
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Statistics implements Modifier
{
    protected QueryBuilder $queryBuilder;

    public function __construct(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Enables the query's debug mode to get more detailed information.
     *
     * @param Query $query The query to modify
     * @return Query The modified query with enabled debugging mode
     */
    public function modifyQuery(Query $query): Query
    {
        return $this->queryBuilder->startFrom($query)->useDebug(true)->getQuery();
    }
}
