<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Ingo Renner <ingo@typo3.org>
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


/**
 * Index Inspector to see what documents have been indexed for a selected page.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_mod_index_IndexInspector extends t3lib_extobjbase {

	/**
	 * The parent Web -> Info module's template
	 *
	 * @var	template
	 */
	private $document;

	/**
	 * The current page ID.
	 *
	 * @var	integer
	 */
	private $pageId;

	/**
	 * Search
	 *
	 * @var	tx_solr_Search
	 */
	protected $search = NULL;

	/**
	 * The action to execute.
	 *
	 * @var	string
	 */
	protected $action = 'index';

	/**
	 * ExtJs Namespace
	 *
	 * @var	string
	 */
	protected $extjsNamespace = 'TYPO3.tx_solr.IndexInspector';

	/**
	 * "Controller" method
	 *
	 * @return	string	The module's content.
	 */
	public function main() {
		$content = '';

		if ($this->pObj->id <= 0) {
				// return if there's no page id
			return $content;
		}

		$this->initialize();
		$content = $this->listIndexDocuments();;

		return $content;
	}

	protected function initialize() {
		$this->pageId   = $this->pObj->id;
		$this->document = $this->pObj->doc;

		$this->initializeExtJs();
	}

	protected function initializeExtJs() {
		$pageRenderer = $this->document->getPageRenderer();

		$pageRenderer->loadExtJS();
		$pageRenderer->addInlineSettingArray(
			$this->extjsNamespace,
			array(
				'pageId' => $this->pageId,
			)
		);

		$extJsExtensionCorePath = $this->document->backPath . '../t3lib/js/extjs/ux/';
		if (file_exists($extJsExtensionCorePath . 'Ext.grid.RowExpander.js')) {
				// TYPO3 4.5 and higher
			$pageRenderer->addJsFile($extJsExtensionCorePath . 'Ext.grid.RowExpander.js');
		} else {
			$pageRenderer->addJsFile($this->document->backPath . $GLOBALS['PATHrel_solr'] . 'resources/javascript/extjs/ux/Ext.grid.RowExpander.js');
		}

		$pageRenderer->addJsFile($this->document->backPath . $GLOBALS['PATHrel_solr'] . 'resources/javascript/extjs/override/gridpanel.js');
		$pageRenderer->addJsFile($this->document->backPath . $GLOBALS['PATHrel_solr'] . 'resources/javascript/mod_index/index_inspector.js');

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
	 * @return	string	HTML table of documents indexed for the current page.
	 */
	protected function listIndexDocuments() {
		$content = '';

		$content .= $this->document->sectionHeader('Index Inspector');
		$content .= '<div id="indexInspectorDocumentList"></div>';

		return $content;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/mod_index/class.tx_solr_mod_index_indexinspector.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/mod_index/class.tx_solr_mod_index_indexinspector.php']);
}

?>