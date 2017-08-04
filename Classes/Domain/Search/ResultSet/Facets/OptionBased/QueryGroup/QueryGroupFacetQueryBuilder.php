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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetQueryBuilderInterface;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

class QueryGroupFacetQueryBuilder implements FacetQueryBuilderInterface
{

    /**
     * @param string $facetName
     * @param TypoScriptConfiguration $configuration
     * @return array
     */
    public function build($facetName, TypoScriptConfiguration $configuration)
    {
        $facetParameters = [];
        $facetConfiguration = $configuration->getSearchFacetingFacetByName($facetName);

        foreach ($facetConfiguration['queryGroup.'] as $queryName => $queryConfiguration) {
            $tag = '';
            if ($configuration->getSearchFacetingKeepAllFacetsOnSelection()) {
                // TODO This code is duplicated from "Query/Modifier/Faceting.php"
                // Eventually the "exclude fields" should get passed to this method beforehand instead
                // of generating them in each different "buildFacetParameters" implementation
                $facets = [];
                foreach ($configuration->getSearchFacetingFacets() as $facet) {
                    $facets[] = $facet['field'];
                }
                $tag = '{!ex=' . implode(',', $facets) . '}';
            } elseif ($facetConfiguration['keepAllOptionsOnSelection'] == 1) {
                $tag = '{!ex=' . $facetConfiguration['field'] . '}';
            }

            $facetParameters['facet.query'][] = $tag . $facetConfiguration['field'] . ':' . $queryConfiguration['query'];
        }

        return $facetParameters;
    }
}
