<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\QueryGroup;

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
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;

/**
 * Class QueryGroupFacetParser
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class QueryGroupFacetParser extends AbstractFacetParser
{

    /**
     * @param SearchResultSet $resultSet
     * @param string $facetName
     * @param array $facetConfiguration
     * @return QueryGroupFacet|null
     */
    public function parse(SearchResultSet $resultSet, $facetName, array $facetConfiguration)
    {
        $response = $resultSet->getResponse();
        $fieldName = $facetConfiguration['field'];
        $label = $this->getPlainLabelOrApplyCObject($facetConfiguration);

        $rawOptions = $this->getRawOptions($response, $fieldName);
        $noOptionsInResponse = $rawOptions === [];
        $hideEmpty = !$resultSet->getUsedSearchRequest()->getContextTypoScriptConfiguration()->getSearchFacetingShowEmptyFacetsByName($facetName);

        if ($noOptionsInResponse && $hideEmpty) {
            return null;
        }

        /** @var QueryGroupFacet $facet */
        $facet = $this->objectManager->get(
            QueryGroupFacet::class,
            $resultSet,
            $facetName,
            $fieldName,
            $label,
            $facetConfiguration
        );

        $activeFacets = $resultSet->getUsedSearchRequest()->getActiveFacetNames();
        $facet->setIsUsed(in_array($facetName, $activeFacets, true));

        if (!$noOptionsInResponse) {
            $facet->setIsAvailable(true);
            foreach ($rawOptions as $query => $count) {
                $value = $this->getValueByQuery($query, $facetConfiguration);
                // Skip unknown queries
                if ($value === null) {
                    continue;
                }

                if ($this->getIsExcludedFacetValue($query, $facetConfiguration)) {
                    continue;
                }

                $isOptionsActive = $resultSet->getUsedSearchRequest()->getHasFacetValue($facetName, $value);
                $label = $this->getLabelFromRenderingInstructions(
                    $value,
                    $count,
                    $facetName,
                    $facetConfiguration
                );
                $facet->addOption($this->objectManager->get(Option::class, $facet, $label, $value, $count, $isOptionsActive));
            }
        }


        // after all options have been created we apply a manualSortOrder if configured
        // the sortBy (lex,..) is done by the solr server and triggered by the query, therefore it does not
        // need to be handled in the frontend.
        $this->applyManualSortOrder($facet, $facetConfiguration);
        $this->applyReverseOrder($facet, $facetConfiguration);

        return $facet;
    }

    /**
     * Get raw query options
     *
     * @param ResponseAdapter $response
     * @param string $fieldName
     * @return array
     */
    protected function getRawOptions(ResponseAdapter $response, $fieldName)
    {
        $options = [];

        foreach ($response->facet_counts->facet_queries as $rawValue => $count) {
            if ((int)$count === 0) {
                continue;
            }

            // todo: add test cases to check if this is needed https://forge.typo3.org/issues/45440
            // remove tags from the facet.query response, for facet.field
            // and facet.range Solr does that on its own automatically
            $rawValue = preg_replace('/^\{!ex=[^\}]*\}(.*)/', '\\1', $rawValue);

            list($field, $query) = explode(':', $rawValue, 2);
            if ($field === $fieldName) {
                $options[$query] = $count;
            }
        }

        return $options;
    }

    /**
     * @param string $query
     * @param array $facetConfiguration
     * @return string|null
     */
    protected function getValueByQuery($query, array $facetConfiguration)
    {
        $value = null;
        foreach ($facetConfiguration['queryGroup.'] as $valueKey => $config) {
            if (isset($config['query']) && $config['query'] === $query) {
                $value = rtrim($valueKey, '.');
                break;
            }
        }
        return $value;
    }
}
