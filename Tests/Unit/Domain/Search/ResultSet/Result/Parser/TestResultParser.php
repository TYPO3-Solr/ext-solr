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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Domain\Search\ResultSet\Result\Parser;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\AbstractResultParser;
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
     * @return SearchResultSet
     */
    public function parse(SearchResultSet $resultSet, bool $useRawDocuments = true): SearchResultSet
    {
        // TODO: Implement parse() method.
        return $resultSet;
    }

    /**
     * @param SearchResultSet $resultSet
     * @return mixed
     */
    public function canParse(SearchResultSet $resultSet): bool
    {
        return true;
    }
}
