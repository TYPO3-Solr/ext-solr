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

use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * A facet option
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class FacetOption
{

    /**
     * Facet name.
     *
     * @var string
     */
    protected $facetName;

    /**
     * Facet option value
     *
     * @var int|string
     */
    protected $value;

    /**
     * Facet option value encoded by a ApacheSolrForTypo3\Solr\Query\FilterEncoder\FilterEncoder for use in
     * URLs.
     *
     * @var string
     */
    protected $urlValue = '';

    /**
     * Number of results that will be returned when applying this facet
     * option's filter to the query.
     *
     * @var int
     */
    protected $numberOfResults;

    /**
     * The current facet's configuration.
     *
     * @var array
     */
    protected $facetConfiguration;

    /**
     * Constructor.
     *
     * @param string $facetName Facet Name
     * @param int|string $facetOptionValue Facet option value
     * @param int $facetOptionNumberOfResults number of results to be returned when applying this option's filter
     */
    public function __construct(
        $facetName,
        $facetOptionValue,
        $facetOptionNumberOfResults = 0
    ) {
        $this->facetName = $facetName;
        $this->value = $facetOptionValue;
        $this->numberOfResults = intval($facetOptionNumberOfResults);

        $solrConfiguration = Util::getSolrConfiguration();
        $this->facetConfiguration = $solrConfiguration->getSearchFacetingFacetByName($this->facetName);
    }

    /**
     * Renders a single facet option according to the facet's rendering
     * instructions that may have been configured.
     *
     * @return string The facet option rendered according to rendering instructions if available
     */
    public function render()
    {
        $renderedFacetOption = $this->value;

        if (isset($this->facetConfiguration['renderingInstruction'])) {
            $contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
            $contentObject->start(array(
                'optionValue' => $this->value,
                'optionCount' => $this->numberOfResults,
                'facetName' => $this->facetName,
            ));

            $renderedFacetOption = $contentObject->cObjGetSingle(
                $this->facetConfiguration['renderingInstruction'],
                $this->facetConfiguration['renderingInstruction.']
            );
        }

        return htmlspecialchars($renderedFacetOption);
    }

    /**
     * Checks whether the facet option has been selected in a facet by the user
     * by checking the GET values in the URL.
     *
     * @param string $facetName Facet name to check against.
     * @return bool TRUE if the option is selected, FALSE otherwise
     */
    public function isSelectedInFacet($facetName)
    {
        $isSelected = false;

        $resultParameters = GeneralUtility::_GET('tx_solr');
        $filterParameters = [];
        if (isset($resultParameters['filter'])) {
            $filterParameters = (array)array_map('urldecode',
                $resultParameters['filter']);
        }

        foreach ($filterParameters as $filter) {
            list($filterName, $filterValue) = explode(':', $filter);

            if ($filterName == $facetName && $filterValue == $this->getUrlValue()) {
                $isSelected = true;
                break;
            }
        }

        return $isSelected;
    }

    /**
     * Gets the option's value for use in URLs
     *
     * @return string The option's URL value.
     */
    public function getUrlValue()
    {
        $urlValue = $this->urlValue;

        if (empty($urlValue)) {
            $urlValue = $this->value;
        }

        return $urlValue;
    }

    /**
     * Sets the option's value for use in URLs
     *
     * @param string $urlValue The option's URL value.
     */
    public function setUrlValue($urlValue)
    {
        $this->urlValue = $urlValue;
    }

    /**
     * Gets the option's value.
     *
     * @return int|string The option's value.
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Gets the number of results this option yields when applied to the query.
     *
     * @return int Number of results
     */
    public function getNumberOfResults()
    {
        return $this->numberOfResults;
    }
}
