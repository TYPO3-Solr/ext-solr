<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
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
 * Proxy class for \Apache_Solr_Document to customize \Apache_Solr_Document without
 * changing the library code.
 *
 * Implements
 * @deprecated This class was moved to the \Domain\Search\ResultSet\Result package, please use this one. Will be removed in 9.0
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class SearchResult extends \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\SearchResult
{
    /**
     * @deprecated This class was moved to the \Domain\Search\ResultSet\Result package, please use this one. Will be removed in 9.0
     *
     * @param \Apache_Solr_Document $document
     * @param bool $throwExceptions
     */
    public function __construct(\Apache_Solr_Document $document, $throwExceptions = false)
    {
        GeneralUtility::logDeprecatedFunction();
        parent::__construct($document, $throwExceptions);
    }
}
