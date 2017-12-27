<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result;

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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The SearchResultBuilder is responsible to build a SearchResult object from an Apache_Solr_Document
 * and should use a different class as SearchResult if configured.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\ResultSet
 */
class SearchResultBuilder {

    /**
     * This method is used to wrap the \Apache_Solr_Document instance in an instance of the configured SearchResult
     * class.
     *
     * @param \Apache_Solr_Document $originalDocument
     * @throws \InvalidArgumentException
     * @return SearchResult
     */
    public function fromApacheSolrDocument(\Apache_Solr_Document $originalDocument)
    {
        $searchResultClassName = $this->getResultClassName();
        $result = GeneralUtility::makeInstance($searchResultClassName, $originalDocument);
        if (!$result instanceof SearchResult) {
            throw new \InvalidArgumentException('Could not create result object with class: ' . (string)$searchResultClassName, 1470037679);
        }

        return $result;
    }

    /**
     * @return string
     */
    protected function getResultClassName()
    {
        return isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName ']) ?
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '] : SearchResult::class;
    }
}