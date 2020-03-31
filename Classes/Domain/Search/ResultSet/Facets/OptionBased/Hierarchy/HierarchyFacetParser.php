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
use ApacheSolrForTypo3\Solr\System\Solr\ParsingUtil;

/**
 * Class HierarchyFacetParser
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
        $optionsFromSolrResponse = isset($response->facet_counts->facet_fields->{$fieldName}) ? ParsingUtil::getMapArrayFromFlatArray($response->facet_counts->facet_fields->{$fieldName}) : [];
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

        if ($this->facetOptionsMustBeResorted($facetConfiguration)) {
            $nodesToCreate = $this->sortFacetOptionsInNaturalOrder($nodesToCreate);
        }

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
     * Sorts facet options in natural order.
     * Options must be sorted in natural order,
     * because lower nesting levels must be instantiated first, to serve as parents for higher nested levels.
     * See implementation of HierarchyFacet::createNode().
     *
     * @param array $flatOptionsListForFacet
     * @return void sorted list of facet options
     */
    protected function sortFacetOptionsInNaturalOrder(array $flatOptionsListForHierarchyFacet)
    {
        uksort($flatOptionsListForHierarchyFacet, "strnatcmp");
        return $flatOptionsListForHierarchyFacet;
    }

    /**
     * Checks if options must be resorted.
     *
     * Apache Solr facet.sort can be set globally or per facet.
     * Relevant TypoScript paths:
     * plugin.tx_solr.search.faceting.sortBy causes facet.sort Apache Solr parameter
     * plugin.tx_solr.search.faceting.facets.[facetName].sortBy causes f.<fieldname>.facet.sort parameter
     *
     * see: https://lucene.apache.org/solr/guide/6_6/faceting.html#Faceting-Thefacet.sortParameter
     * see: https://wiki.apache.org/solr/SimpleFacetParameters#facet.sort : "This parameter can be specified on a per field basis."
     *
     * @param array $facetConfiguration
     * @return bool
     */
    protected function facetOptionsMustBeResorted(array $facetConfiguration)
    {
        if (isset($facetConfiguration['sortBy']) && $facetConfiguration['sortBy'] === 'index') {
            return true;
        }

        return false;
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
                $valueFromRequest = HierarchyTool::substituteSlashes($valueFromRequest);
                $valueFromRequest = trim($valueFromRequest, '/');
                $valueFromRequest = (count(explode('/', $valueFromRequest)) - 1) . '-' . $valueFromRequest . '/';
                $valueFromRequest = HierarchyTool::unSubstituteSlashes($valueFromRequest);
            }
            $activeFacetValues[] = $valueFromRequest;
        }
        return $activeFacetValues;
    }
}
