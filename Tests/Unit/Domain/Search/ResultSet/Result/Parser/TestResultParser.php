<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Result\Parser;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\AbstractResultParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\DefaultResultParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResultCollection;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * Fake test parser
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class TestResultParser extends AbstractResultParser
{

    /**
     * @param SearchResultSet $resultSet
     * @param bool $useRawDocuments
     * @return SearchResultCollection
     */
    public function parse(SearchResultSet $resultSet, bool $useRawDocuments = true)
    {
        // TODO: Implement parse() method.
    }

    /**
     * @param SearchResultSet $resultSet
     * @return mixed
     */
    public function canParse(SearchResultSet $resultSet)
    {
        return true;
    }
}