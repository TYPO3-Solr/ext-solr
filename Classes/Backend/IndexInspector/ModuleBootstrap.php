<?php
namespace ApacheSolrForTypo3\Solr\Backend\IndexInspector;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Rafael Kähm <rafael.kaehm@dkd.de>
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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Core\Bootstrap;

/**
 * This class is a wrapper for Web->Info controller of solr Index Inspector.
 * It is registered in ext_tables.php with \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction()
 * and called by the info extension via SCbase functionality.
 *
 * Extbase currently provides no way to register a "TBE_MODULES_EXT"(third level module) module directly,
 * therefore we need to bootstrap extbase on our own here to jump to the Web->Info controller.
 * @todo: Remove this class and register 'ApacheSolrForTypo3\Solr\Controller\ApacheSolrDocumentController' in proper Extbase way if Web>Info is implemented with Extbase. See the class description before doing something.
 */
class ModuleBootstrap
{

    /**
     * Dummy method, called by SCbase external object handling
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * Dummy method, called by SCbase external object handling
     *
     * @return void
     */
    public function checkExtObj()
    {
    }

    /**
     * Bootstrap extbase and jump to WebInfo controller
     *
     * @return string
     */
    public function main()
    {
        $configuration = [
            'vendorName' => 'ApacheSolrForTypo3',
            'extensionName' => 'Solr',
            'pluginName' => 'searchbackend_SolrInfo'
        ];
        // Yeah, this is ugly. But currently, there is no other direct way
        // in extbase to force a specific controller in backend mode.
        // Overwriting $_GET was the most simple solution here until extbase
        // provides a clean way to solve this.

        $_GET['tx_solr_searchbackend_solrinfo']['controller'] = 'Backend\\Web\\Info\\ApacheSolrDocument';
        /* @var $extbaseBootstrap Bootstrap */
        $extbaseBootstrap = GeneralUtility::makeInstance(Bootstrap::class);
        return $extbaseBootstrap->run('', $configuration);
    }
}
