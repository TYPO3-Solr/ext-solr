<?php
namespace ApacheSolrForTypo3\Solr\Backend\IndexInspector;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Search;
use TYPO3\CMS\Backend\Module\AbstractFunctionModule;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;

/**
 * Index Inspector to see what documents have been indexed for a selected page.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class IndexInspector extends AbstractFunctionModule
{

    /**
     * Search
     *
     * @var Search
     */
    protected $search = null;
    /**
     * The action to execute.
     *
     * @var string
     */
    protected $action = 'index';
    /**
     * ExtJs Namespace
     *
     * @var string
     */
    protected $extjsNamespace = 'TYPO3.tx_solr.IndexInspector';
    /**
     * The parent Web -> Info module's template
     *
     * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
     */
    private $document;
    /**
     * The current page ID.
     *
     * @var int
     */
    private $pageId;

    /**
     * "Controller" method
     *
     * @return string The module's content.
     */
    public function main()
    {
        $content = '';

        if ($this->pObj->id <= 0) {
            // return if there's no page id
            return $content;
        }

        $this->initialize();
        $content = $this->listIndexDocuments();

        return $content;
    }

    protected function initialize()
    {
        $this->pageId = $this->pObj->id;
        $this->document = $this->pObj->doc;

        $this->initializeExtJs();
    }

    protected function initializeExtJs()
    {

        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);

        $pageRenderer->loadExtJS();
        $pageRenderer->addInlineSettingArray(
            $this->extjsNamespace,
            [
                'pageId' => $this->pageId,
            ]
        );

        $pageRenderer->addExtDirectCode(['TYPO3.tx_solr.IndexInspector.Remote']);

        // @Todo This should be solved otherwise see #948 - for now a copy of
        // Ext.grid.RowExpander.js from 7.6.x has been included
        if (VersionNumberUtility::convertVersionNumberToInteger(TYPO3_branch) >= VersionNumberUtility::convertVersionNumberToInteger('8.0')) {
            $pageRenderer->addJsFile('EXT:solr/Resources/JavaScript/ExtJs/Ext.grid.RowExpander.js');
            $pageRenderer->addJsFile('EXT:solr/Resources/JavaScript/ExtJs/override/gridpanel.js');
            $pageRenderer->addJsFile('EXT:solr/Resources/JavaScript/ModIndex/index_inspector.js');
        } else {
            $pageRenderer->addJsFile(ExtensionManagementUtility::extRelPath('backend') . 'Resources/Public/JavaScript/extjs/ux/Ext.grid.RowExpander.js');
            $pageRenderer->addJsFile(ExtensionManagementUtility::extRelPath('solr') . 'Resources/JavaScript/ExtJs/override/gridpanel.js');
            $pageRenderer->addJsFile(ExtensionManagementUtility::extRelPath('solr') . 'Resources/JavaScript/ModIndex/index_inspector.js');
        }

        $pageRenderer->addCssInlineBlock('grid-selection-enabler', '
			.x-selectable, .x-selectable * {
				-moz-user-select: text!important;
				-khtml-user-select: text!important;
			}
		');
    }

    // actions

    /**
     * Queries Solr for this page's documents and lists them in a table.
     *
     * @return string HTML table of documents indexed for the current page.
     */
    protected function listIndexDocuments()
    {
        $content = '';

        $content .= $this->document->sectionHeader('Index Inspector');
        $content .= '<div id="indexInspectorDocumentList"></div>';

        return $content;
    }
}
