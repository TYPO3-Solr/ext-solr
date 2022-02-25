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

namespace ApacheSolrForTypo3\Solr\Controller;

use ApacheSolrForTypo3\Solr\Domain\Search\Suggest\SuggestService;

use ApacheSolrForTypo3\Solr\System\Solr\SolrUnavailableException;
use ApacheSolrForTypo3\Solr\Util;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class SuggestController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @copyright (c) 2017 Timo Hund <timo.hund@dkd.de>
 */
class SuggestController extends AbstractBaseController
{
    /**
     * This method creates a suggest json response that can be used in a suggest layer.
     *
     * @param string $queryString
     * @param string|null $callback
     * @param array|null $additionalFilters
     * @return ResponseInterface
     * @throws AspectNotFoundException
     */
    public function suggestAction(string $queryString, ?string $callback = null, ?array $additionalFilters = []): ResponseInterface
    {
        // Get suggestions
        $rawQuery = htmlspecialchars(mb_strtolower(trim($queryString)));

        try {
            /** @var SuggestService $suggestService */
            $suggestService = GeneralUtility::makeInstance(
                SuggestService::class,
                /** @scrutinizer ignore-type */
                $this->typoScriptFrontendController,
                /** @scrutinizer ignore-type */
                $this->searchService,
                /** @scrutinizer ignore-type */
                $this->typoScriptConfiguration
            );

            $additionalFilters = is_array($additionalFilters) ? array_map('htmlspecialchars', $additionalFilters) : [];
            $pageId = $this->typoScriptFrontendController->getRequestedId();
            $languageId = Util::getLanguageUid();
            $arguments = $this->request->getArguments();

            $searchRequest = $this->getSearchRequestBuilder()->buildForSuggest($arguments, $rawQuery, $pageId, $languageId);
            $result = $suggestService->getSuggestions($searchRequest, $additionalFilters);
        } catch (SolrUnavailableException $e) {
            $this->logSolrUnavailable();
            $result = ['status' => false];
            return $this->htmlResponse(json_encode($result, JSON_UNESCAPED_SLASHES))->withStatus(503, self::STATUS_503_MESSAGE);
        }
        if ($callback) {
            return $this->htmlResponse(htmlspecialchars($callback) . '(' . json_encode($result, JSON_UNESCAPED_SLASHES) . ')');
        }
        return $this->htmlResponse(json_encode($result, JSON_UNESCAPED_SLASHES));
    }
}
