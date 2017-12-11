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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\DefaultFacetQueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetQueryBuilderInterface;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\SortingExpression;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * Class OptionsFacetQueryBuilder
 *
 * The Options facet query builder builds the facets as json structure
 *
 * @Todo: When we use json faceting for other facets some logic of this class can be moved to the base class.
 *
 * @package ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options
 */
class OptionsFacetQueryBuilder extends DefaultFacetQueryBuilder implements FacetQueryBuilderInterface {

    /**
     * @param string $facetName
     * @param TypoScriptConfiguration $configuration
     * @return array
     */
    public function build($facetName, TypoScriptConfiguration $configuration)
    {
        $facetParameters = [];
        $facetConfiguration = $configuration->getSearchFacetingFacetByName($facetName);

        $jsonFacetOptions = [
            'type' => 'terms',
            'field' => $facetConfiguration['field'],
        ];

        $jsonFacetOptions['limit'] = $this->buildLimitForJson($facetConfiguration, $configuration);
        $jsonFacetOptions['mincount'] = $this->buildMincountForJson($facetConfiguration, $configuration);

        $sorting = $this->buildSortingForJson($facetConfiguration);
        if (!empty($sorting)) {
            $jsonFacetOptions['sort'] = $sorting;
        }

        if (is_array($facetConfiguration['metrics.'])) {
            foreach ($facetConfiguration['metrics.'] as $key => $value) {
                $jsonFacetOptions['facet']['metrics_' . $key] = $value;
            }
        }

        $excludeTags = $this->buildExcludeTagsForJson($facetConfiguration, $configuration);
        if (!empty($excludeTags)) {
            $jsonFacetOptions['domain']['excludeTags'] = $excludeTags;
        }

        $facetParameters['json.facet'][$facetName] = $jsonFacetOptions;

        return $facetParameters;
    }

    /**
     * @param array $facetConfiguration
     * @param TypoScriptConfiguration $configuration
     * @return string
     */
    protected function buildExcludeTagsForJson(array $facetConfiguration, TypoScriptConfiguration $configuration)
    {
        $isKeepAllOptionsActiveForSingleFacet = $facetConfiguration['keepAllOptionsOnSelection'] == 1;
        $isKeepAllOptionsActiveGlobalsAndCountsEnabled = $configuration->getSearchFacetingKeepAllFacetsOnSelection()
            && $configuration->getSearchFacetingCountAllFacetsForSelection();

        if ($isKeepAllOptionsActiveForSingleFacet || $isKeepAllOptionsActiveGlobalsAndCountsEnabled) {
            return $facetConfiguration['field'];
        } else {
            // keepAllOptionsOnSelection globally active
            $facets = [];
            foreach ($configuration->getSearchFacetingFacets() as $facet) {
                $facets[] = $facet['field'];
            }
            return implode(',', $facets);
        }
    }

    /**
     * @param array $facetConfiguration
     * @param TypoScriptConfiguration $configuration
     * @return int
     */
    protected function buildLimitForJson(array $facetConfiguration, TypoScriptConfiguration $configuration)
    {
        if (isset($facetConfiguration['facetLimit'])) {
            return (int)$facetConfiguration['facetLimit'];
        } elseif ($configuration->getSearchFacetingFacetLimit() > 0) {
            return $configuration->getSearchFacetingFacetLimit();
        } else {
            return -1;
        }
    }

    /**
     * @param array $facetConfiguration
     * @param TypoScriptConfiguration $configuration
     * @return int
     */
    protected function buildMincountForJson(array $facetConfiguration, TypoScriptConfiguration $configuration)
    {
        if (isset($facetConfiguration['minimumCount'])) {
            return (int)$facetConfiguration['minimumCount'];
        } elseif ($configuration->getSearchFacetingMinimumCount() > 0) {
            return $configuration->getSearchFacetingMinimumCount();
        } else {
            return 1;
        }
    }

    /**
     * @param array $facetConfiguration
     * @return string
     */
    protected function buildSortingForJson(array $facetConfiguration) {
        if (isset($facetConfiguration['sortBy'])) {
            $sortingExpression = new SortingExpression();
            $sorting = $facetConfiguration['sortBy'];
            $direction = $facetConfiguration['sortDirection'];
            return $sortingExpression->getForJsonFacet($sorting, $direction);
        }
        return '';
    }
}
