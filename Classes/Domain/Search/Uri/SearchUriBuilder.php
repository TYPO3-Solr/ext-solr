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
     * @var int
     */
    protected static $hitCount;

    /**
     * @var int
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
            ->getCopyForSubRequest()->addFacetValue($facetName, $facetValue)
            ->getAsArray();

        $additionalArguments = $this->getAdditionalArgumentsFromRequestConfiguration($previousSearchRequest);
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
            ->getCopyForSubRequest()->removeAllFacetValuesByName($facetName);

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
            ->getCopyForSubRequest()->removeFacetValue($facetName, $facetValue)
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
            ->getCopyForSubRequest()->removeAllFacetValuesByName($facetName)
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
            ->getCopyForSubRequest()->removeAllFacets()
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
     * @param $queryString
     * @return string
     */
    public function getNewSearchUri(SearchRequest $previousSearchRequest, $queryString)
    {
        /** @var $request SearchRequest */
        $contextConfiguration = $previousSearchRequest->getContextTypoScriptConfiguration();
        $contextSystemLanguage = $previousSearchRequest->getContextSystemLanguageUid();
        $contextPageUid = $previousSearchRequest->getContextPageUid();

        $request = GeneralUtility::makeInstance(SearchRequest::class, [], $contextPageUid, $contextSystemLanguage, $contextConfiguration);
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
     * @return int|null
     */
    protected function getTargetPageUidFromRequestConfiguration(SearchRequest $request)
    {
        if ($request->getContextTypoScriptConfiguration() == null) {
            return null;
        }

        return $request->getContextTypoScriptConfiguration()->getSearchTargetPage();
    }

    /**
     * @param int $pageUid
     * @param array $arguments
     * @return string
     */
    protected function buildLinkWithInMemoryCache($pageUid, array $arguments)
    {
        $values = [];
        $structure = $arguments;
        $this->getSubstitution($structure, $values);
        $hash = md5($pageUid . json_encode($structure));

        if (isset(self::$preCompiledLinks[$hash])) {
            self::$hitCount++;
            $template = self::$preCompiledLinks[$hash];
        } else {
            self::$missCount++;
            $this->uriBuilder->setTargetPageUid($pageUid);
            $template = $this->uriBuilder->setArguments($structure)->setUseCacheHash(false)->build();
            self::$preCompiledLinks[$hash] = $template;
        }

        $keys = array_map(function ($value) {
            return urlencode($value);
        }, array_keys($values));
        $values = array_map(function ($value) {
            return urlencode($value);
        }, $values);
        $uri = str_replace($keys, $values, $template);
        return $uri;
    }

    /**
     * This method is used to build two arrays from a nested array. The first one represents the structure.
     * In this structure the values are replaced with the pass to the value. At the same time the values get collected
     * in the $values array, with the path as key. This can be used to build a comparable hash from the arguments
     * in order to reduce the amount of typolink calls
     *
     *
     * Example input
     *
     * $data = [
     *  'foo' => [
     *      'bar' => 111
     *   ]
     * ]
     *
     * will return:
     *
     * $structure = [
     *  'foo' => [
     *      'bar' => '###foo:bar###'
     *   ]
     * ]
     *
     * $values = [
     *  '###foo:bar###' => 111
     * ]
     *
     * @param $structure
     * @param $values
     * @param array $branch
     */
    protected function getSubstitution(array &$structure, array  &$values, array $branch = [])
    {
        foreach ($structure as $key => &$value) {
            $branch[] = $key;
            if (is_array($value)) {
                $this->getSubstitution($value, $values, $branch);
            } else {
                $path = '###' . implode(':', $branch) . '###';
                $values[$path] = $value;
                $structure[$key] = $path;
            }
        }
    }
}
