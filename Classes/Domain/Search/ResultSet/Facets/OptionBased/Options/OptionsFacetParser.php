<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options;

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
class OptionsFacetParser extends AbstractFacetParser
{
    /**
     * @param SearchResultSet $resultSet
     * @param string $facetName
     * @param array $facetConfiguration
     * @return OptionsFacet|null
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

        /** @var $facet OptionsFacet */
        $facet = $this->objectManager->get(
            OptionsFacet::class,
            $resultSet,
            $facetName,
            $fieldName,
            $label,
            $facetConfiguration
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
            $facet->addOption(new Option($facet, $label, $optionsValue, $count, $isOptionsActive));
        }

        // after all options have been created we apply a manualSortOrder if configured
        // the sortBy (lex,..) is done by the solr server and triggered by the query, therefore it does not
        // need to be handled in the frontend.
        $facet = $this->applyManualSortOrder($facet, $facetConfiguration);

        $facet = $this->applyReverseOrder($facet, $facetConfiguration);

        return $facet;
    }
}
