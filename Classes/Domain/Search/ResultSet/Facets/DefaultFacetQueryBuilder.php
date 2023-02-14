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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets;

use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

class DefaultFacetQueryBuilder implements FacetQueryBuilderInterface
{
    /**
     * @param string $facetName
     * @param TypoScriptConfiguration $configuration
     * @return array
     */
    public function build(string $facetName, TypoScriptConfiguration $configuration): array
    {
        $facetParameters = [];
        $facetConfiguration = $configuration->getSearchFacetingFacetByName($facetName);

        $tags = $this->buildExcludeTags($facetConfiguration, $configuration);
        $facetParameters['facet.field'][] = $tags . $facetConfiguration['field'];

        $sortingExpression = new SortingExpression();
        $facetSortExpression = $sortingExpression->getForFacet($facetConfiguration['sortBy'] ?? '');
        if (!empty($facetSortExpression)) {
            $facetParameters['f.' . $facetConfiguration['field'] . '.facet.sort'] = $facetSortExpression;
        }

        return $facetParameters;
    }

    /**
     * @param array $facetConfiguration
     * @param TypoScriptConfiguration $configuration
     * @return string
     */
    protected function buildExcludeTags(array $facetConfiguration, TypoScriptConfiguration $configuration): string
    {
        // simple for now, may add overrides f.<field_name>.facet.* later
        if ($configuration->getSearchFacetingKeepAllFacetsOnSelection()) {
            $facets = [];
            foreach ($configuration->getSearchFacetingFacets() as $facet) {
                $facets[] = $facet['field'];
            }

            return '{!ex=' . implode(',', $facets) . '}';
        }
        if (($facetConfiguration['keepAllOptionsOnSelection'] ?? null) == 1) {
            return '{!ex=' . $facetConfiguration['field'] . '}';
        }

        return '';
    }
}
