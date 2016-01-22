<?php
namespace ApacheSolrForTypo3\Solr\ViewHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Stefan Sprenger <stefan.sprenger@dkd.de>
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

/**
 * Subpart viewhelper class to render facets
 *
 * @author Stefan Sprenger <stefan.sprenger@dkd.de>
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Facet extends AbstractSubpartViewHelper
{

    /**
     * TypoScript configuration of tx_solr
     *
     * @var array
     */
    protected $configuration = null;

    /**
     * Constructor
     *
     */
    public function __construct(array $arguments = array())
    {
        if (is_null($this->configuration)) {
            $this->configuration = Util::getSolrConfiguration();
        }
    }

    /**
     * Returns the configured targetPage and does a fallback on the current page if nothing was configured.
     *
     * @return integer
     */
    protected function getTargetPageId()
    {
        return (int) ($this->configuration['search.']['targetPage'] ? $this->configuration['search.']['targetPage'] : $GLOBALS['TSFE']->id);
    }

    /**
     * Renders a facet.
     *
     * @param array $arguments
     * @return string
     */
    public function execute(array $arguments = array())
    {
        $facetName = trim($arguments[0]);
        $configuredFacets = $this->configuration['search.']['faceting.']['facets.'];
        $facetContent = '';
        $template = clone $this->template;
        $search = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Search');

        if (!array_key_exists($facetName . '.', $configuredFacets)) {
            throw new \UnexpectedValueException(
                'Tried rendering facet "' . $facetName . '", no configuration found.',
                1329138206
            );
        }

        if ($search->hasSearched()) {
            $facetRendererFactory = GeneralUtility::makeInstance(
                'ApacheSolrForTypo3\\Solr\\Facet\\FacetRendererFactory',
                $configuredFacets
            );

            $facet = GeneralUtility::makeInstance(
                'ApacheSolrForTypo3\\Solr\\Facet\\Facet',
                $facetName,
                $facetRendererFactory->getFacetInternalType($facetName)
            );

            $facetRenderer = $facetRendererFactory->getFacetRendererByFacet($facet);
            $facetRenderer->setTemplate($this->template);

            $targetPageId = $this->getTargetPageId();
            $facetRenderer->setLinkTargetPageId($targetPageId);

            $facet = $facetRenderer->getFacetProperties();
            $template->addVariable('facet', $facet);

            $facetContent = $facetRenderer->renderFacet();
        }

        $template->addSubpart('single_facet', $facetContent);

        return $template->render();
    }
}
