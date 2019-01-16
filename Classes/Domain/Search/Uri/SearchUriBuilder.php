<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Uri;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;

/**
 * SearchUriBuilder
 *
 * Responsibility:
 *
 * The SearchUriBuilder is responsible to build uris, that are used in the
 * searchContext. It can use the previous request with it's persistent
 * arguments to build the url for a search sub request.
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\Domain\Search\Uri
 */

class SearchUriBuilder
{

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @var array
     */
    protected static $preCompiledLinks = [];

    /**
     * @var integer
     */
    protected static $hitCount;

    /**
     * @var integer
     */
    protected static $missCount;

    /**
     * @var array
     */
    protected static $additionalArgumentsCache = [];

    /**
     * @param UriBuilder $uriBuilder
     */
    public function injectUriBuilder(UriBuilder $uriBuilder)
    {
        $this->uriBuilder = $uriBuilder;
    }

    /**
     * @param SearchRequest $previousSearchRequest
     * @param $facetName
     * @param $facetValue
     * @return string
     */
    public function getAddFacetValueUri(SearchRequest $previousSearchRequest, $facetName, $facetValue)
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->removeAllGroupItemPages()->addFacetValue($facetName, $facetValue)
            ->getAsArray();

        $additionalArguments = $this->getAdditionalArgumentsFromRequestConfiguration($previousSearchRequest);
        $additionalArguments = is_array($additionalArguments) ? $additionalArguments : [];

        $arguments = $persistentAndFacetArguments + $additionalArguments;

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $arguments);
    }

    /**
     * Removes all other facet values for this name and only set's the passed value for the facet.
     *
     * @param SearchRequest $previousSearchRequest
     * @param $facetName
     * @param $facetValue
     * @return string
     */
    public function getSetFacetValueUri(SearchRequest $previousSearchRequest, $facetName, $facetValue)
    {
        $previousSearchRequest = $previousSearchRequest
            ->getCopyForSubRequest()->removeAllGroupItemPages()->removeAllFacetValuesByName($facetName);

        return $this->getAddFacetValueUri($previousSearchRequest, $facetName, $facetValue);
    }

    /**
     * @param SearchRequest $previousSearchRequest
     * @param $facetName
     * @param $facetValue
     * @return string
     */
    public function getRemoveFacetValueUri(SearchRequest $previousSearchRequest, $facetName, $facetValue)
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->removeAllGroupItemPages()->removeFacetValue($facetName, $facetValue)
            ->getAsArray();

        $additionalArguments = [];
        if ($previousSearchRequest->getContextTypoScriptConfiguration()->getSearchFacetingFacetLinkUrlParametersUseForFacetResetLinkUrl()) {
            $additionalArguments = $this->getAdditionalArgumentsFromRequestConfiguration($previousSearchRequest);
        }
        $arguments = $persistentAndFacetArguments + $additionalArguments;

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $arguments);
    }

    /**
     * @param SearchRequest $previousSearchRequest
     * @param $facetName
     * @return string
     */
    public function getRemoveFacetUri(SearchRequest $previousSearchRequest, $facetName)
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->removeAllGroupItemPages()->removeAllFacetValuesByName($facetName)
            ->getAsArray();

        $additionalArguments = [];
        if ($previousSearchRequest->getContextTypoScriptConfiguration()->getSearchFacetingFacetLinkUrlParametersUseForFacetResetLinkUrl()) {
            $additionalArguments = $this->getAdditionalArgumentsFromRequestConfiguration($previousSearchRequest);
        }

        $arguments = $persistentAndFacetArguments + $additionalArguments;

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $arguments);
    }

    /**
     * @param SearchRequest $previousSearchRequest
     * @return string
     */
    public function getRemoveAllFacetsUri(SearchRequest $previousSearchRequest)
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->removeAllGroupItemPages()->removeAllFacets()
            ->getAsArray();

        $additionalArguments = [];
        if ($previousSearchRequest->getContextTypoScriptConfiguration()->getSearchFacetingFacetLinkUrlParametersUseForFacetResetLinkUrl()) {
            $additionalArguments = $this->getAdditionalArgumentsFromRequestConfiguration($previousSearchRequest);
        }

        $arguments = $persistentAndFacetArguments + $additionalArguments;

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $arguments);
    }

    /**
     * @param SearchRequest $previousSearchRequest
     * @param $page
     * @return string
     */
    public function getResultPageUri(SearchRequest $previousSearchRequest, $page)
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->setPage($page)
            ->getAsArray();

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $persistentAndFacetArguments);
    }

    /**
     * @param SearchRequest $previousSearchRequest
     * @param GroupItem $groupItem
     * @param int $page
     * @return string
     */
    public function getResultGroupItemPageUri(SearchRequest $previousSearchRequest, GroupItem $groupItem, int $page)
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->setGroupItemPage($groupItem->getGroup()->getGroupName(), $groupItem->getGroupValue(), $page)
            ->getAsArray();
        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $persistentAndFacetArguments);
    }
    /**
     * @param SearchRequest $previousSearchRequest
     * @param $queryString
     * @return string
     */
    public function getNewSearchUri(SearchRequest $previousSearchRequest, $queryString)
    {
        /** @var $request SearchRequest */
        $contextConfiguration = $previousSearchRequest->getContextTypoScriptConfiguration();
        $contextSystemLanguage = $previousSearchRequest->getContextSystemLanguageUid();
        $contextPageUid = $previousSearchRequest->getContextPageUid();

        $request = GeneralUtility::makeInstance(
            SearchRequest::class, [],
            /** @scrutinizer ignore-type */ $contextPageUid,
            /** @scrutinizer ignore-type */ $contextSystemLanguage,
            /** @scrutinizer ignore-type */ $contextConfiguration);
        $arguments = $request->setRawQueryString($queryString)->getAsArray();

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $arguments);
    }

    /**
     * @param SearchRequest $previousSearchRequest
     * @param $sortingName
     * @param $sortingDirection
     * @return string
     */
    public function getSetSortingUri(SearchRequest $previousSearchRequest, $sortingName, $sortingDirection)
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->setSorting($sortingName, $sortingDirection)
            ->getAsArray();

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $persistentAndFacetArguments);
    }

    /**
     * @param SearchRequest $previousSearchRequest
     * @return string
     */
    public function getRemoveSortingUri(SearchRequest $previousSearchRequest)
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()->removeSorting()
            ->getAsArray();

        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $persistentAndFacetArguments);
    }

    /**
     * @param SearchRequest $previousSearchRequest
     * @return string
     */
    public function getCurrentSearchUri(SearchRequest $previousSearchRequest)
    {
        $persistentAndFacetArguments = $previousSearchRequest
            ->getCopyForSubRequest()
            ->getAsArray();


        $pageUid = $this->getTargetPageUidFromRequestConfiguration($previousSearchRequest);
        return $this->buildLinkWithInMemoryCache($pageUid, $persistentAndFacetArguments);
    }

    /**
     * @param SearchRequest $request
     * @return array
     */
    protected function getAdditionalArgumentsFromRequestConfiguration(SearchRequest $request)
    {
        if ($request->getContextTypoScriptConfiguration() == null) {
            return [];
        }

        $reQuestId = $request->getId();
        if (isset(self::$additionalArgumentsCache[$reQuestId])) {
            return self::$additionalArgumentsCache[$reQuestId];
        }

        self::$additionalArgumentsCache[$reQuestId] = $request->getContextTypoScriptConfiguration()
            ->getSearchFacetingFacetLinkUrlParametersAsArray();

        return self::$additionalArgumentsCache[$reQuestId];
    }

    /**
     * @param SearchRequest $request
     * @return integer|null
     */
    protected function getTargetPageUidFromRequestConfiguration(SearchRequest $request)
    {
        if ($request->getContextTypoScriptConfiguration() == null) {
            return null;
        }

        return $request->getContextTypoScriptConfiguration()->getSearchTargetPage();
    }

    /**
     * @param integer $pageUid
     * @param array $arguments
     * @return string
     */
    protected function buildLinkWithInMemoryCache($pageUid, array $arguments)
    {
        $hash = md5($pageUid . '|' . json_encode($arguments));

        if (isset(self::$preCompiledLinks[$hash])) {
            self::$hitCount++;
            $uri = self::$preCompiledLinks[$hash];
        } else {
            self::$missCount++;
            $this->uriBuilder->setTargetPageUid($pageUid);
            $uri = $this->uriBuilder->setArguments($arguments)->setUseCacheHash(true)->build();
            self::$preCompiledLinks[$hash] = $uri;
        }

        return $uri;
    }
}
