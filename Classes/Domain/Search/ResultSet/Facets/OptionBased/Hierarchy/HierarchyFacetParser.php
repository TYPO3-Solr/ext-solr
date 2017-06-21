<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Hierarchy;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetParser;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSet;

/**
 * Class OptionsFacetParser
 */
class HierarchyFacetParser extends AbstractFacetParser
{
    /**
     * @param SearchResultSet $resultSet
     * @param string $facetName
     * @param array $facetConfiguration
     * @return HierarchyFacet|null
     */
    public function parse(SearchResultSet $resultSet, $facetName, array $facetConfiguration)
    {
        $response = $resultSet->getResponse();
        $fieldName = $facetConfiguration['field'];
        $label = $this->getPlainLabelOrApplyCObject($facetConfiguration);
        $optionsFromSolrResponse = isset($response->facet_counts->facet_fields->{$fieldName}) ? get_object_vars($response->facet_counts->facet_fields->{$fieldName}) : [];
        $optionsFromRequest = $this->getActiveFacetValuesFromRequest($resultSet, $facetName);
        $hasOptionsInResponse = !empty($optionsFromSolrResponse);
        $hasSelectedOptionsInRequest = count($optionsFromRequest) > 0;
        $hasNoOptionsToShow = !$hasOptionsInResponse && !$hasSelectedOptionsInRequest;
        $hideEmpty = !$resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchFacetingShowEmptyFacetsByName($facetName);

        if ($hasNoOptionsToShow && $hideEmpty) {
            return null;
        }

        /** @var $facet HierarchyFacet */
        $facet = $this->objectManager->get(HierarchyFacet::class, $resultSet, $facetName, $fieldName, $label, $facetConfiguration);

        $hasActiveOptions = count($optionsFromRequest) > 0;
        $facet->setIsUsed($hasActiveOptions);

        $facet->setIsAvailable($hasOptionsInResponse);

        $nodesToCreate = $this->getMergedFacetValueFromSearchRequestAndSolrResponse($optionsFromSolrResponse, $optionsFromRequest);

        foreach ($nodesToCreate as $value => $count) {
            if ($this->getIsExcludedFacetValue($value, $facetConfiguration)) {
                continue;
            }
            $isActive = in_array($value, $optionsFromRequest);
            $delimiterPosition = strpos($value, '-');
            $path = substr($value, $delimiterPosition + 1);
            $pathArray = $this->getPathAsArray($path);
            $key = array_pop($pathArray);
            $parentKey = array_pop($pathArray);
            $value = '/' . $path;
            $label = $this->getLabelFromRenderingInstructions($key, $count, $facetName, $facetConfiguration);

            $facet->createNode($parentKey, $key, $label, $value, $count, $isActive);
        }

        return $facet;
    }

    /**
     * This method is used to get the path array from a hierarchical facet. It substitutes escaped slashes to keep them
     * when they are used inside a facetValue.
     *
     * @param string $path
     * @return array
     */
    protected function getPathAsArray($path)
    {
        $path = str_replace('\/', '@@@', $path);
        $path = rtrim($path, "/");
        $segments = explode('/', $path);
        return array_map(function($item) {
            return str_replace('@@@', '/', $item);
        }, $segments);
    }

    /**
     * Retrieves the active facetValue for a facet from the search request.
     * @param SearchResultSet $resultSet
     * @param string $facetName
     * @return array
     */
    protected function getActiveFacetValuesFromRequest(SearchResultSet $resultSet, $facetName)
    {
        $activeFacetValues = [];
        $values = $resultSet->getUsedSearchRequest()->getActiveFacetValuesByName($facetName);

        foreach (is_array($values) ? $values : [] as $valueFromRequest) {
            // Attach the 'depth' param again to the value
            if (strpos($valueFromRequest, '-') === false) {
                $valueFromRequest = trim($valueFromRequest, '/');
                $valueFromRequest = (count(explode('/', $valueFromRequest)) - 1) . '-' . $valueFromRequest . '/';
            }
            $activeFacetValues[] = $valueFromRequest;
        }
        return $activeFacetValues;
    }
}
