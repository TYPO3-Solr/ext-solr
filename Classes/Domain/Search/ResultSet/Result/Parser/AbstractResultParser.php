<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * A ResultParser is responsible to create the result object structure from the \Apache_Solr_Response
 * and assign it to the SearchResultSet.
 */
abstract class AbstractResultParser {

    /**
     * @var SearchResultBuilder
     */
    protected $searchResultBuilder;

    /**
     * @var DocumentEscapeService
     */
    protected $documentEscapeService;

    /**
     * AbstractResultParser constructor.
     * @param SearchResultBuilder|null $resultBuilder
     * @param DocumentEscapeService|null $documentEscapeService
     */
    public function __construct(SearchResultBuilder $resultBuilder = null, DocumentEscapeService $documentEscapeService = null) {
        $this->searchResultBuilder = $resultBuilder ?? GeneralUtility::makeInstance(SearchResultBuilder::class);
        $this->documentEscapeService = $documentEscapeService ?? GeneralUtility::makeInstance(DocumentEscapeService::class);
    }

    /**
     * @param SearchResultSet $resultSet
     * @param bool $useRawDocuments
     * @return SearchResultSet
     */
    abstract public function parse(SearchResultSet $resultSet, bool $useRawDocuments = true);

    /**
     * @param SearchResultSet $resultSet
     * @return mixed
     */
    abstract public function canParse(SearchResultSet $resultSet);
}
