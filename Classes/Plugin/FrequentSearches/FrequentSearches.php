<?php
namespace ApacheSolrForTypo3\Solr\Plugin\FrequentSearches;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\CommandResolver;
use ApacheSolrForTypo3\Solr\Template;
use ApacheSolrForTypo3\Solr\Plugin\CommandPluginBase;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Plugin 'Frequent Searches' for the 'solr' extension.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class FrequentSearches extends CommandPluginBase
{

    /**
     * Path to this script relative to the extension dir.
     */
    public $scriptRelPath = 'Classes/Plugin/FrequentSearches/FrequentSearches.php';

    /**
     * Returns an initialized commandResolver. In this case we use the command
     * of the results view.
     *
     * @todo currently the commands of the result view are used, we should discuss if we use own command here
     * @return CommandResolver A command resolver
     */
    protected function getCommandResolver()
    {
        return GeneralUtility::makeInstance(CommandResolver::class);
    }

    /**
     * Retrieves the list of commands we have to process for the results view
     *
     * @return array Array of command names to process for the result view
     */
    protected function getCommandList()
    {
        $commandList = CommandResolver::getPluginCommands(
            'frequentsearches'
        );

        return $commandList;
    }

    /**
     * Perform the action for the plugin. In this case it does not do anything
     * as the plugin simply renders the frequent searches command.
     *
     * @return void
     */
    protected function performAction()
    {
    }

    /**
     * Post initialization of the template engine.
     *
     * @param Template $template
     * @return Template
     */
    protected function postInitializeTemplateEngine(Template $template)
    {
        $template->addVariable('tx_solr', $this->getSolrVariables());

        return $template;
    }

    /**
     * Gets a list of EXT:solr variables like the prefix ID.
     *
     * @return array array of EXT:solr variables
     */
    protected function getSolrVariables()
    {
        $currentUrl = $this->getCurrentUrlWithQueryLinkBuilder();

        return [
            'prefix' => $this->prefixId,
            'current_url' => $currentUrl
        ];
    }

    /**
     * Provides the typoscript key, which is used to determine the template file
     * for this view.
     *
     * @return string TypoScript key used to determine the template file.
     */
    protected function getTemplateFileKey()
    {
        return 'frequentSearches';
    }

    /**
     * Return the plugin key, used to initialize the template engine.
     *
     * @return string Plugin key used during initialization of the template engine
     */
    protected function getPluginKey()
    {
        return 'PiFrequentSearches';
    }

    /**
     * Returns the name of the template subpart used by the plugin.
     *
     * @return string Name of the template subpart to use for rendering
     */
    protected function getSubpart()
    {
        return 'solr_search';
    }

    /**
     * Implementation of preRender() method. Used to include CSS files.
     *
     */
    protected function preRender()
    {
        $cssFile = $this->typoScriptConfiguration->getCssFileByFileKey('results');
        if ($cssFile !== '') {
            $cssFile = GeneralUtility::createVersionNumberedFilename($GLOBALS['TSFE']->tmpl->getFileName($cssFile));
            $GLOBALS['TSFE']->additionalHeaderData['tx_solr-resultsCss'] =
                '<link href="' . $cssFile . '" rel="stylesheet" type="text/css" />';
        }
    }
}
