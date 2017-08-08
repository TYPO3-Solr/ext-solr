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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Facet renderer factory, creates facet renderers depending on the configured
 * type of a facet.
 *
 * @deprecated Not supported with fluid templating, will be removed in 8.0
 * @author Ingo Renner <ingo@typo3.org>
 */
class FacetRendererFactory
{

    /**
     * Register a facet type with its helper classes.
     *
     * @deprecated Not supported with fluid templating, please use FacetRegistry instead. will be removed in 8.0
     * @param string $facetType Facet type that can be used in a TypoScript facet configuration
     * @param string $rendererClassName Class used to render the facet UI
     * @param string $filterEncoderClassName Class used to translate filter parameter from the URL to Lucene filter syntax
     * @param string $queryFacetBuilderClassName Class used to build the facet parameters according to the facet's configuration
     */
    public static function registerFacetType($facetType, $rendererClassName, $filterEncoderClassName = '', $queryFacetBuilderClassName = '')
    {
        GeneralUtility::logDeprecatedFunction();
    }
}