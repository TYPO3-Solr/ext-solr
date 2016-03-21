<?php
namespace ApacheSolrForTypo3\Solr\Plugin\Results;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2015 Hans Höchtl <hans.hoechtl@typovision.de>
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

use ApacheSolrForTypo3\Solr\Plugin\CommandPluginAware;
use ApacheSolrForTypo3\Solr\Plugin\CommandPluginBase;
use ApacheSolrForTypo3\Solr\Plugin\FormModifier;
use ApacheSolrForTypo3\Solr\Template;
use ApacheSolrForTypo3\Solr\Util;

/**
 * QueryAnalyzer form modifier, outputs parsed lucene query
 *
 * @author Hans Höchtl <hans.hoechtl@typovision.de>
 * @package TYPO3
 * @subpackage solr
 */
class QueryAnalyzerFormModifier implements FormModifier, CommandPluginAware
{

    /**
     * Configuration
     *
     * @var array
     */
    protected $configuration;

    /**
     * The currently active plugin
     *
     * @var CommandPluginBase
     */
    protected $parentPlugin;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->configuration = Util::getSolrConfiguration();
    }

    /**
     * Sets the currently active parent plugin.
     *
     * @param CommandPluginBase $parentPlugin Currently active parent plugin
     */
    public function setParentPlugin(CommandPluginBase $parentPlugin)
    {
        $this->parentPlugin = $parentPlugin;
    }

    /**
     * Modifies the search form by providing an additional marker showing
     * the parsed lucene query used by Solr.
     *
     * @param array $markers An array of existing form markers
     * @param Template $template An instance of the template engine
     * @return array Array with additional markers for queryAnalysis
     */
    public function modifyForm(array $markers, Template $template)
    {
        $markers['debug_query'] = '<br><strong>Parsed Query:</strong><br>' .
            $this->parentPlugin->getSearchResultSetService()->getSearch()->getDebugResponse()->parsedquery;

        return $markers;
    }
}
