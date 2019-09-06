<?php

namespace ApacheSolrForTypo3\Solr\Controller;

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

use ApacheSolrForTypo3\Solr\Domain\Search\Suggest\SuggestService;
use ApacheSolrForTypo3\Solr\System\Solr\SolrUnavailableException;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SuggestController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\Controller
 */
class SuggestController extends AbstractBaseController
{
    /**
     * This method creates a suggest json response that can be used in a suggest layer.
     *
     * @param string $queryString
     * @param string $callback
     * @param array $additionalFilters
     * @return string
     */
    public function suggestAction($queryString, $callback, $additionalFilters = [])
    {
        $jsonPCallback = htmlspecialchars($callback);
        // Get suggestions
        $rawQuery = htmlspecialchars(mb_strtolower(trim($queryString)));

        try {
            /** @var SuggestService $suggestService */
            $suggestService = GeneralUtility::makeInstance(
                SuggestService::class,
                /** @scrutinizer ignore-type */ $this->typoScriptFrontendController,
                /** @scrutinizer ignore-type */ $this->searchService,
                /** @scrutinizer ignore-type */ $this->typoScriptConfiguration);

            $additionalFilters = is_array($additionalFilters) ? array_map("htmlspecialchars", $additionalFilters) : [];
            $pageId = $this->typoScriptFrontendController->getRequestedId();
            $languageId = Util::getLanguageUid();
            $arguments = (array)$this->request->getArguments();

            $searchRequest = $this->getSearchRequestBuilder()->buildForSuggest($arguments, $rawQuery, $pageId, $languageId);
            $result = $suggestService->getSuggestions($searchRequest, $additionalFilters);
        } catch (SolrUnavailableException $e) {
            $this->handleSolrUnavailable();
            $result = ['status' => false];
        }

        return htmlspecialchars($jsonPCallback) . '(' . json_encode($result) . ')';
    }

}
