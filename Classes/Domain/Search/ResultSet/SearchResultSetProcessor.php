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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet;

/**
 * The implementation can be used to influence a SearchResultSet that is
 * created and processed in the SearchResultSetService.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
interface SearchResultSetProcessor
{
    /**
     * The implementation can be used to influence a SearchResultSet that is
     * created and processed in the SearchResultSetService.
     *
     * @param SearchResultSet $resultSet
     * @return mixed
     */
    public function process(SearchResultSet $resultSet): SearchResultSet;
}
