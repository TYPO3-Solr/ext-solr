<?php
namespace ApacheSolrForTypo3\Solr\Facet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Query\FilterEncoder\QueryGroup;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Query group facet renderer.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class QueryGroupFacetRenderer extends SimpleFacetRenderer
{

    /**
     * Provides the internal type of facets the renderer handles.
     * The type is one of field, range, or query.
     *
     * @return string Facet internal type
     */
    public static function getFacetInternalType()
    {
        return Facet::TYPE_QUERY;
    }

    /**
     * Encodes the facet option values from raw Lucene queries to values that
     * can be easily used in rendering instructions and URL generation.
     *
     * (non-PHPdoc)
     * @see \ApacheSolrForTypo3\Solr\Facet\AbstractFacetRenderer::getFacetOptions()
     */
    public function getFacetOptions()
    {
        $facetOptions = [];
        $facetOptionsRaw = parent::getFacetOptions();

        $filterEncoder = GeneralUtility::makeInstance(QueryGroup::class);
        foreach ($facetOptionsRaw as $facetOption => $numberOfResults) {
            $facetOption = $filterEncoder->encodeFilter($facetOption,
                $this->facetConfiguration);
            $facetOptions[$facetOption] = $numberOfResults;
        }

        return $facetOptions;
    }
}
