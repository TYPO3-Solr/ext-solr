<?php
namespace ApacheSolrForTypo3\Solr\Plugin\Results;

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

use ApacheSolrForTypo3\Solr\Plugin\CommandPluginBase;
use ApacheSolrForTypo3\Solr\Plugin\PluginCommand;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Results per page switch view command
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class ResultsPerPageSwitchCommand implements PluginCommand
{

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
     * Constructor.
     *
     * @param CommandPluginBase $parentPlugin Parent plugin object.
     */
    public function __construct(CommandPluginBase $parentPlugin)
    {
        $this->parentPlugin = $parentPlugin;
        $this->configuration = $parentPlugin->typoScriptConfiguration;
    }

    /**
     * @return array|null
     */
    public function execute()
    {
        $markers = array();

        $selectOptions = $this->getResultsPerPageOptions();
        if ($selectOptions) {
            $queryLinkBuilder = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query\\LinkBuilder',
                $this->parentPlugin->getSearchResultSetService()->getSearch()->getQuery());
            $queryLinkBuilder->setLinkTargetPageId($this->parentPlugin->getLinkTargetPageId());
            $form = array(
                'action' => $queryLinkBuilder->getQueryUrl()
            );

            $markers['loop_options|option'] = $selectOptions;
            $markers['form'] = $form;
        } else {
            $markers = null;
        }

        return $markers;
    }

    /**
     * Generates the options for the results per page switch.
     *
     * @return array Array of results per page switch options.
     */
    public function getResultsPerPageOptions()
    {
        $resultsPerPageOptions = array();

        $resultsPerPageSwitchOptions = $this->configuration->getSearchResultsPerPageSwitchOptionsAsArray();
        $currentNumberOfResultsShown = $this->parentPlugin->getSearchResultSetService()->getLastResultSet()->getResultsPerPage();

        $queryLinkBuilder = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query\\LinkBuilder',
            $this->parentPlugin->getSearchResultSetService()->getSearch()->getQuery());
        $queryLinkBuilder->removeUnwantedUrlParameter('resultsPerPage');
        $queryLinkBuilder->setLinkTargetPageId($this->parentPlugin->getLinkTargetPageId());

        foreach ($resultsPerPageSwitchOptions as $option) {
            $selected = '';
            $selectedClass = '';

            if ($option == $currentNumberOfResultsShown) {
                $selected = ' selected="selected"';
                $selectedClass = ' class="currentNumberOfResults"';
            }

            $resultsPerPageOptions[] = array(
                'value' => $option,
                'selected' => $selected,
                'selectedClass' => $selectedClass,
                'url' => $queryLinkBuilder->getQueryUrl(array('resultsPerPage' => $option)),
            );
        }

        return $resultsPerPageOptions;
    }
}
