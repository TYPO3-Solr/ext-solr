<?php
namespace ApacheSolrForTypo3\Solr\Plugin\Results;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Facet\Facet;
use ApacheSolrForTypo3\Solr\Facet\FacetRendererFactory;
use ApacheSolrForTypo3\Solr\Facet\UsedFacetRenderer;
use ApacheSolrForTypo3\Solr\Plugin\CommandPluginBase;
use ApacheSolrForTypo3\Solr\Plugin\PluginCommand;
use ApacheSolrForTypo3\Solr\Query\LinkBuilder;
use ApacheSolrForTypo3\Solr\Search;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * facets view command
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class FacetingCommand implements PluginCommand
{

    /**
     * Search instance
     *
     * @var Search
     */
    protected $search;

    /**
     * Parent plugin
     *
     * @var Results
     */
    protected $parentPlugin;

    /**
     * Configuration
     *
     * @var array
     */
    protected $configuration;

    /**
     * Facets active: TRUE if any option of any facet has been selected.
     *
     * @var bool
     */
    protected $facetsActive = false;

    /**
     * Constructor.
     *
     * @param CommandPluginBase $parentPlugin Parent plugin object.
     */
    public function __construct(CommandPluginBase $parentPlugin)
    {
        $this->search = GeneralUtility::makeInstance(Search::class);

        $this->parentPlugin = $parentPlugin;
        $this->configuration = $parentPlugin->typoScriptConfiguration;
    }

    /**
     * Executes the command, renders the template subpart markers if faceting
     * is activated.
     *
     * @return array|null Array of faceting markers or null if faceting is deactivated
     */
    public function execute()
    {
        $marker = [];

        if ($this->configuration->getSearchFaceting()
            && ($this->search->getNumberOfResults() || $this->configuration->getSearchInitializeWithEmptyQuery() || $this->configuration->getSearchInitializeWithQuery())
        ) {
            $marker['subpart_available_facets'] = $this->renderAvailableFacets();
            $marker['subpart_used_facets'] = $this->renderUsedFacets();
            $marker['active'] = $this->facetsActive ? '1' : '0';
            $marker['search_has_results'] = $this->search->getNumberOfResults() ? 1 : 0;

            $this->addFacetingJavascript();
        }

        if (count($marker) === 0) {
            // in case we didn't fill any markers - like when there are no
            // search results - we set markers to NULL to signal that we
            // want to have the subpart removed completely
            $marker = null;
        }

        return $marker;
    }

    /**
     * Renders user-selectable facets.
     *
     * @return string rendered facets subpart
     */
    protected function renderAvailableFacets()
    {
        $facetContent = '';

        $template = clone $this->parentPlugin->getTemplate();
        $template->workOnSubpart('available_facets');

        $configuredFacets = $this->configuration->getSearchFacetingFacets();

        $facetRendererFactory = GeneralUtility::makeInstance(
            FacetRendererFactory::class,
            $configuredFacets
        );
        /** @var $facetRendererFactory FacetRendererFactory */
        foreach ($configuredFacets as $facetName => $facetConfiguration) {
            $facetName = substr($facetName, 0, -1);
            $facet = GeneralUtility::makeInstance(Facet::class,
                $facetName,
                $facetRendererFactory->getFacetInternalType($facetName)
            );
            /** @var $facet Facet */
            if (
                (isset($facetConfiguration['includeInAvailableFacets']) && $facetConfiguration['includeInAvailableFacets'] == '0')
                || !$facet->isRenderingAllowed()
            ) {
                // don't render facets that should not be included in available facets
                // or that do not meet their requirements to be rendered
                continue;
            }

            $facetRenderer = $facetRendererFactory->getFacetRendererByFacet($facet);
            $facetRenderer->setTemplate($template);
            $facetRenderer->setLinkTargetPageId($this->parentPlugin->getLinkTargetPageId());

            if ($facet->isActive()) {
                $this->facetsActive = true;
            }

            $facetContent .= $facetRenderer->renderFacet();
        }

        $template->addSubpart('single_facet', $facetContent);

        return $template->render();
    }

    /**
     * Renders facets selected by the user.
     *
     * @return string rendered selected facets subpart
     */
    protected function renderUsedFacets()
    {
        $template = clone $this->parentPlugin->getTemplate();
        $template->workOnSubpart('used_facets');

        $query = $this->search->getQuery();

        $queryLinkBuilder = GeneralUtility::makeInstance(LinkBuilder::class,
            $this->search->getQuery());
        /* @var $queryLinkBuilder LinkBuilder */
        $queryLinkBuilder->setLinkTargetPageId($this->parentPlugin->getLinkTargetPageId());

        // URL parameters added to facet URLs may not need to be added to the facets reset URL
        $facetLinkUrlParameters = $this->configuration->getSearchFacetingFacetLinkUrlParametersAsArray();
        $useForFacetResetLink = $this->configuration->getSearchFacetingFacetLinkUrlParametersUseForFacetResetLinkUrl();

        if (count($facetLinkUrlParameters) > 0 && $useForFacetResetLink) {
            $addedUrlParameterKeys = array_keys($facetLinkUrlParameters);

            foreach ($addedUrlParameterKeys as $addedUrlParameterKey) {
                if (GeneralUtility::isFirstPartOfStr($addedUrlParameterKey, 'tx_solr')) {
                    $addedUrlParameterKey = substr($addedUrlParameterKey, 8, -1);
                    $queryLinkBuilder->addUnwantedUrlParameter($addedUrlParameterKey);
                }
            }
        }

        $resultParameters = GeneralUtility::_GET('tx_solr');
        $filterParameters = [];
        if (isset($resultParameters['filter'])) {
            $filterParameters = (array)array_map('urldecode',
                $resultParameters['filter']);
        }

        $facetsInUse = [];
        foreach ($filterParameters as $filter) {
            // only split by the first ":" to allow the use of colons in the filter value
            list($filterName, $filterValue) = explode(':', $filter, 2);

            $facetConfiguration = $this->configuration->getSearchFacetingFacetByName($filterName);

            // don't render facets that should not be included in used facets
            if (isset($facetConfiguration['includeInUsedFacets']) && $facetConfiguration['includeInUsedFacets'] == '0') {
                continue;
            }

            $usedFacetRenderer = GeneralUtility::makeInstance(
                UsedFacetRenderer::class,
                $filterName,
                $filterValue,
                $filter,
                $this->parentPlugin->getTemplate(),
#FIXME usage of $query
                $query
            );
            $usedFacetRenderer->setLinkTargetPageId($this->parentPlugin->getLinkTargetPageId());

            $facetToRemove = $usedFacetRenderer->render();

            $facetsInUse[] = $facetToRemove;
        }
        $template->addLoop('facets_in_use', 'remove_facet', $facetsInUse);

        $template->addVariable('remove_all_facets', array(
            'url' => $queryLinkBuilder->getQueryUrl(array('filter' => [])),
            'text' => '###LLL:faceting_removeAllFilters###'
        ));

        $content = '';
        if (count($facetsInUse)) {
            $content = $template->render();
        }

        return $content;
    }

    /**
     * Adds the JavaScript necessary for some of the faceting features;
     * folding/unfolding a list of facet options that exceed the configured
     * limit of visible options
     *
     * @return void
     */
    protected function addFacetingJavascript()
    {
        $javascriptManager = $this->parentPlugin->getJavascriptManager();

        $expansionLabels = '
			var tx_solr_facetLabels = {
				\'showMore\' : \'' . $this->parentPlugin->pi_getLL('faceting_showMore') . '\',
				\'showFewer\' : \'' . $this->parentPlugin->pi_getLL('faceting_showFewer') . '\'
			};
		';
        $javascriptManager->addJavascript('tx_solr-facetingExpansionLabels',
            $expansionLabels);
        $javascriptManager->loadFile('faceting.limitExpansion');
    }
}
