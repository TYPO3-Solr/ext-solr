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
 */
class OptionsFacetQueryBuilder extends DefaultFacetQueryBuilder implements FacetQueryBuilderInterface
{
    /**
     * Builds query parts for options facet
     */
    public function build(string $facetName, TypoScriptConfiguration $configuration): array
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

        if (is_array($facetConfiguration['metrics.'] ?? null)) {
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
     * Builds exclude tags for Apache Solr query
     */
    protected function buildExcludeTagsForJson(array $facetConfiguration, TypoScriptConfiguration $configuration): string
    {
        $excludeFields = [];

        if ($configuration->getSearchFacetingKeepAllFacetsOnSelection()) {
            if (!$configuration->getSearchFacetingCountAllFacetsForSelection()) {
                // keepAllOptionsOnSelection globally active
                foreach ($configuration->getSearchFacetingFacets() as $facet) {
                    $excludeFields[] = $facet['field'];
                }
            } else {
                $excludeFields[] = $facetConfiguration['field'];
            }
        }

        $isKeepAllOptionsActiveForSingleFacet = ($facetConfiguration['keepAllOptionsOnSelection'] ?? null) == 1;
        if ($isKeepAllOptionsActiveForSingleFacet) {
            $excludeFields[] = $facetConfiguration['field'];
        }

        if (!empty($facetConfiguration['additionalExcludeTags'])) {
            $excludeFields[] = $facetConfiguration['additionalExcludeTags'];
        }

        return implode(',', array_unique($excludeFields));
    }

    /**
     * Builds limit params for options facet
     */
    protected function buildLimitForJson(array $facetConfiguration, TypoScriptConfiguration $configuration): int
    {
        return (int)($facetConfiguration['facetLimit'] ?? ($configuration->getSearchFacetingFacetLimit() ?? -1));
    }

    /**
     * Builds limit params for json facet
     */
    protected function buildMincountForJson(array $facetConfiguration, TypoScriptConfiguration $configuration): int
    {
        return (int)($facetConfiguration['minimumCount'] ?? ($configuration->getSearchFacetingMinimumCount() ?? 1));
    }

    /**
     * Builds sortings params for json facet
     */
    protected function buildSortingForJson(array $facetConfiguration): string
    {
        if (isset($facetConfiguration['sortBy'])) {
            $sortingExpression = new SortingExpression();
            $sorting = $facetConfiguration['sortBy'];
            $direction = $facetConfiguration['sortDirection'] ?? '';
            return $sortingExpression->getForJsonFacet((string)$sorting, $direction);
        }
        return '';
    }
}
