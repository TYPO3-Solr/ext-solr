<?php
namespace ApacheSolrForTypo3\Solr\Facet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2011 Markus Goldbach <markus.goldbach@dkd.de>
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

use ApacheSolrForTypo3\Solr\Query\FilterEncoder\Hierarchy;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;


/**
 * Renderer for hierarchical facets.
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 */
class HierarchicalFacetRenderer extends AbstractFacetRenderer
{

    /**
     * Parent content object, set when called by ContentObjectRenderer->callUserFunction()
     *
     * @var ContentObjectRenderer
     */
    public $cObj;


    /**
     * Provides the internal type of facets the renderer handles.
     * The type is one of field, range, or query.
     *
     * @return string Facet internal type
     */
    public static function getFacetInternalType()
    {
        return Facet::TYPE_FIELD;
    }

    /**
     * Renders the complete hierarchical facet.
     *
     * @return string Facet markup.
     */
    protected function renderFacetOptions()
    {
        $facetContent = '';
        $facetOptions = $this->getFacetOptions();

        /* @var $filterEncoder Hierarchy */
        $filterEncoder = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query\\FilterEncoder\\Hierarchy');

        // enrich the facet options with links before building the menu structure
        $enrichedFacetOptions = array();
        foreach ($facetOptions as $facetOptionValue => $facetOptionResultCount) {
            $facetOption = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Facet\\FacetOption',
                $this->facetName,
                $facetOptionValue,
                $facetOptionResultCount
            );

            /* @var $facetOption FacetOption */
            $facetOption->setUrlValue($filterEncoder->encodeFilter($facetOptionValue));

            $facetLinkBuilder = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Facet\\LinkBuilder',
                $this->search->getQuery(), $this->facetName, $facetOption);

            $optionSelected = $facetOption->isSelectedInFacet($this->facetName);
            $optionLinkUrl = $facetLinkBuilder->getAddFacetOptionUrl();

            // negating the facet option links to remove a filter
            if ($this->facetConfiguration['selectingSelectedFacetOptionRemovesFilter'] && $optionSelected) {
                $optionLinkUrl = $facetLinkBuilder->getRemoveFacetOptionUrl();
            }

            if ($this->facetConfiguration['singleOptionMode']) {
                $optionLinkUrl = $facetLinkBuilder->getReplaceFacetOptionUrl();
            }

            // by default the facet link builder creates htmlspecialchars()ed URLs
            // HMENU will also apply htmlspecialchars(), to prevent corrupt URLs
            // we're reverting the facet builder's htmlspecials() here
            $optionLinkUrl = htmlspecialchars_decode($optionLinkUrl);

            $enrichedFacetOptions[$facetOption->getValue()] = array(
                'numberOfResults' => $facetOption->getNumberOfResults(),
                'url' => $optionLinkUrl,
                'selected' => $optionSelected,
            );
        }

        $facetContent .= $this->renderHierarchicalFacet($enrichedFacetOptions);

        return $facetContent;
    }

    /**
     * Renders the actual hierarchical facet, usually handing the rendering job
     * off to a HMENU content object.
     *
     * @param array $facetOptions Available facet options.
     * @return string Hierarchical facet rendered by a cObject
     */
    protected function renderHierarchicalFacet($facetOptions)
    {
        // assuming a rendering instruction is always set for hierarchical facets
        // passing field name and facet options to the necessary userFunc
        /* @var $contentObject ContentObjectRenderer */
        $contentObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
        $contentObject->start(array(
            'facetFieldName' => $this->facetConfiguration['field'],
            'facetOptions' => $facetOptions
        ));

        if (!isset($this->facetConfiguration['hierarchy.']['special'])) {
            // pre-setting some configuration needed to turn the facet options into a menu structure
            $this->facetConfiguration['hierarchy.']['special'] = 'userfunction';
            $this->facetConfiguration['hierarchy.']['special.']['userFunc'] = 'ApacheSolrForTypo3\Solr\Facet\HierarchicalFacetHelper->getMenuStructure';
        }

        $renderedFacet = $contentObject->cObjGetSingle(
            $this->facetConfiguration['hierarchy'],
            $this->facetConfiguration['hierarchy.']
        );

        return $renderedFacet;
    }

    /**
     * Takes the hierarchical facet option, splits it up and returns the last
     * path segment from the hierarchy
     *
     * @param string $facetOptionKey A complete hierarchical facet option
     * @return string The last path segment of the hierarchical facet option
     */
    public static function getLastPathSegmentFromHierarchicalFacetOption(
        $facetOptionKey
    ) {
        // first remove the level indicator in front of the path
        $facetOptionKey = trim($facetOptionKey, '"');
        list(, $path) = explode('-', $facetOptionKey, 2);

        $explodedPath = explode('/', $path);
        $lastPathSegment = $explodedPath[count($explodedPath) - 1];

        return $lastPathSegment;
    }

}
