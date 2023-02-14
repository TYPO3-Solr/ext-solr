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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException;

/**
 * Class OptionsFacetParser
 */
class OptionsFacetParser extends AbstractFacetParser
{
    /**
     * @var Dispatcher|null
     */
    protected ?Dispatcher $dispatcher = null;

    /**
     * @param Dispatcher $dispatcher
     */
    public function injectDispatcher(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param SearchResultSet $resultSet
     * @param string $facetName
     * @param array $facetConfiguration
     * @return OptionsFacet|null
     * @throws InvalidSlotException
     * @throws InvalidSlotReturnException
     */
    public function parse(SearchResultSet $resultSet, string $facetName, array $facetConfiguration): ?AbstractFacet
    {
        $response = $resultSet->getResponse();
        $fieldName = $facetConfiguration['field'];
        $label = $this->getPlainLabelOrApplyCObject($facetConfiguration);
        $optionsFromSolrResponse = $this->getOptionsFromSolrResponse($facetName, $response);
        $metricsFromSolrResponse = $this->getMetricsFromSolrResponse($facetName, $response);
        $optionsFromRequest = $this->getActiveFacetValuesFromRequest($resultSet, $facetName);
        $hasOptionsInResponse = !empty($optionsFromSolrResponse);
        $hasSelectedOptionsInRequest = count($optionsFromRequest) > 0;
        $hasNoOptionsToShow = !$hasOptionsInResponse && !$hasSelectedOptionsInRequest;
        $hideEmpty = !$resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchFacetingShowEmptyFacetsByName($facetName);

        if ($hasNoOptionsToShow && $hideEmpty) {
            return null;
        }

        /** @var $facet OptionsFacet */
        $facet = GeneralUtility::makeInstance(
            OptionsFacet::class,
            $resultSet,
            $facetName,
            $fieldName,
            $label,
            $facetConfiguration,
            $this->objectManager
        );

        $hasActiveOptions = count($optionsFromRequest) > 0;
        $facet->setIsUsed($hasActiveOptions);
        $facet->setIsAvailable($hasOptionsInResponse);

        $optionsToCreate = $this->getMergedFacetValueFromSearchRequestAndSolrResponse($optionsFromSolrResponse, $optionsFromRequest);
        foreach ($optionsToCreate as $optionsValue => $count) {
            if ($this->getIsExcludedFacetValue($optionsValue, $facetConfiguration)) {
                continue;
            }

            $isOptionsActive = in_array($optionsValue, $optionsFromRequest);
            $label = $this->getLabelFromRenderingInstructions($optionsValue, $count, $facetName, $facetConfiguration);
            $facet->addOption(
                GeneralUtility::makeInstance(
                    Option::class,
                    $facet,
                    $label,
                    $optionsValue,
                    $count,
                    $isOptionsActive,
                    ($metricsFromSolrResponse[$optionsValue] ?? [])
                )
            );
        }

        // after all options have been created we apply a manualSortOrder if configured
        // the sortBy (lex,..) is done by the solr server and triggered by the query, therefore it does not
        // need to be handled in the frontend.
        $this->applyManualSortOrder($facet, $facetConfiguration);
        $this->applyReverseOrder($facet, $facetConfiguration);

        if (!is_null($this->dispatcher)) {
            $this->dispatcher->dispatch(__CLASS__, 'optionsParsed', [&$facet, $facetConfiguration]);
        }

        return $facet;
    }

    /**
     * @param string $facetName
     * @param ResponseAdapter $response
     * @return array
     */
    protected function getOptionsFromSolrResponse(string $facetName, ResponseAdapter $response): array
    {
        $optionsFromSolrResponse = [];
        if (!isset($response->facets->{$facetName})) {
            return $optionsFromSolrResponse;
        }

        foreach ($response->facets->{$facetName}->buckets as $bucket) {
            $optionValue = $bucket->val;
            $optionCount = $bucket->count;
            $optionsFromSolrResponse[$optionValue] = $optionCount;
        }

        return $optionsFromSolrResponse;
    }

    /**
     * @param string $facetName
     * @param ResponseAdapter $response
     * @return array
     */
    protected function getMetricsFromSolrResponse(string $facetName, ResponseAdapter $response): array
    {
        $metricsFromSolrResponse = [];

        if (!isset($response->facets->{$facetName}->buckets)) {
            return [];
        }

        foreach ($response->facets->{$facetName}->buckets as $bucket) {
            $bucketVariables = get_object_vars($bucket);
            foreach ($bucketVariables as $key => $value) {
                if (strpos($key, 'metrics_') === 0) {
                    $metricsKey = str_replace('metrics_', '', $key);
                    $metricsFromSolrResponse[$bucket->val][$metricsKey] = $value;
                }
            }
        }

        return $metricsFromSolrResponse;
    }
}
