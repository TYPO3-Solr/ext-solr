<?php
namespace ApacheSolrForTypo3\Solr\ResultDocumentModifier;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2012 Stefan Sprenger <stefan.sprenger@dkd.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Provides highlighting of the search words on the document's actual page by
 * adding parameters to a document's URL property.
 *
 *
 * @author Stefan Sprenger <stefan.sprenger@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class SiteHighlighter implements ResultDocumentModifier
{

    /**
     * Modifies the given result document's url field by appending parameters
     * which will result in having the current search terms highlighted on the
     * target page.
     *
     * @param ResultsCommand $resultCommand The search result command
     * @param array $resultDocument The result document's fields as an array
     * @return array The document with fields as array
     */
    public function modifyResultDocument(
        ResultsCommand $resultCommand,
        array $resultDocument
    ) {
        $searchWords = $resultCommand->getParentPlugin()->getSearchResultSetService()->getSearch()->getQuery()->getKeywordsCleaned();

        // remove quotes from phrase searches - they've been escaped by getCleanUserQuery()
        $searchWords = str_replace('&quot;', '', $searchWords);
        $searchWords = GeneralUtility::trimExplode(' ', $searchWords, true);

        $url = $resultDocument['url'];
        $fragment = '';
        if (strpos($url, '#') !== false) {
            $explodedUrl = explode('#', $url);

            $fragment = $explodedUrl[1];
            $url = $explodedUrl[0];
        }
        $url .= (strpos($url, '?') !== false) ? '&' : '?';
        $url .= 'sword_list[]=' . array_shift($searchWords);

        foreach ($searchWords as $word) {
            $url .= '&sword_list[]=' . $word;
        }

        $url .= '&no_cache=1' . ($fragment ? '#' . $fragment : '');

        // eventually, replace the document's URL with the one that enables highlighting
        $resultDocument['url'] = $url;

        return $resultDocument;
    }
}
