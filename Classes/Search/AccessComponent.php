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

namespace ApacheSolrForTypo3\Solr\Search;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Access search component
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class AccessComponent extends AbstractComponent implements QueryAware
{

    /**
     * Solr query
     *
     * @var Query
     */
    protected $query;

    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * AccessComponent constructor.
     * @param QueryBuilder|null
     */
    public function __construct(QueryBuilder $queryBuilder = null)
    {
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(QueryBuilder::class);
    }

    /**
     * Initializes the search component.
     */
    public function initializeSearchComponent()
    {
        $this->query = $this->queryBuilder
            ->startFrom($this->query)
            ->useSiteHashFromTypoScript($GLOBALS['TSFE']->id)
            ->useUserAccessGroups(Util::getFrontendUserGroups())
            ->getQuery();
    }

    /**
     * Provides the extension component with an instance of the current query.
     *
     * @param Query $query Current query
     */
    public function setQuery(Query $query)
    {
        $this->query = $query;
    }
}
