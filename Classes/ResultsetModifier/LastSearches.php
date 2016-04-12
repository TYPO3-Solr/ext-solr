<?php
namespace ApacheSolrForTypo3\Solr\ResultsetModifier;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Plugin\Results\ResultsCommand;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Logs the keywords from the query into the user's session or the database -
 * depending on configuration.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class LastSearches implements ResultSetModifier
{

    /**
     * Does not actually modify the result set, but tracks the search keywords.
     *
     * (non-PHPdoc)
     * @see ResultSetModifier::modifyResultSet()
     * @param \ApacheSolrForTypo3\Solr\Plugin\Results\ResultsCommand $resultCommand
     * @param array $resultSet
     * @return array
     */
    public function modifyResultSet(
        ResultsCommand $resultCommand,
        array $resultSet
    ) {
        $keywords = $resultCommand->getParentPlugin()->getSearchResultSetService()->getSearch()->getQuery()->getKeywordsCleaned();

        $keywords = trim($keywords);
        if (empty($keywords)) {
            return $resultSet;
        }

        $configuration = $resultCommand->getParentPlugin()->getConfiguration();

            /** @var $lastSearchesService \ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesService */
        $lastSearchesService = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\Domain\Search\LastSearches\LastSearchesService',
            $configuration,
            $GLOBALS['TSFE'],
            $GLOBALS['TYPO3_DB']);

        $lastSearchesService->addToLastSearches($keywords);

        return $resultSet;
    }
}
