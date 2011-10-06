<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2011 Ingo Renner <ingo@typo3.org>
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

$GLOBALS['LANG']->includeLLFile('EXT:solr/mod_admin/locallang.xml');
$BE_USER->modAccess($MCONF, 1);


/**
 * Module 'Solr Search' for the 'solr' extension.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class  tx_solr_ModuleAdmin extends t3lib_SCbase {
	var $pageinfo;

	/**
	 * @var	tx_solr_Site
	 */
	protected $site;

	/**
	 * @var	tx_solr_ConnectionManager
	 */
	protected $connectionManager;

	/**
	 * Initializes the Module
	 *
	 * @return	void
	 */
	public function init() {
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

		parent::init();

			// initialize doc
		$this->doc = t3lib_div::makeInstance('template');
		$this->doc->setModuleTemplate(t3lib_extMgm::extPath('solr') . 'mod_admin/mod_admin.html');
		$this->doc->backPath = $BACK_PATH;
		$this->doc->bodyTagId = 'typo3-mod-php';
		$this->doc->bodyTagAdditions = 'class="tx_solr_mod-admin"';
	}

	/**
	 * Builds the drop down menu to select the solr instance we want to
	 * administer.
	 *
	 * @return	void
	 */
	public function menuConfig() {
		$registry = t3lib_div::makeInstance('t3lib_Registry');
		$sites    = tx_solr_Site::getAvailableSites();

			// TODO add a menu entry on top to manage all indexes, otherwise when selecting a specific index actions will only affect that specific one

		foreach ($sites as $key => $site) {
			$this->MOD_MENU['function'][$site->getRootPageId()] = $site->getLabel();
		}

		parent::menuConfig();
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 * If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	 *
	 * @return	[type]		...
	 */
	public function main() {
		global $BE_USER,$LANG,$BACK_PATH,$TCA_DESCR,$TCA,$CLIENT,$TYPO3_CONF_VARS;

			// Access check!
			// The page will show only if there is a valid page and if this page may be viewed by the user
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id, $this->perms_clause);
		$access = is_array($this->pageinfo) ? 1 : 0;

		$rootPageId = $this->MOD_SETTINGS['function'];

		$this->site              = t3lib_div::makeInstance('tx_solr_Site', $rootPageId);
		$this->connectionManager = t3lib_div::makeInstance('tx_solr_ConnectionManager');

		$docHeaderButtons = $this->getButtons();

		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id)) {

				// Draw the form
			$this->doc->form = '<form action="" method="post" enctype="multipart/form-data">';

				// JavaScript
			$this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{
						document.location = URL;
					}
				</script>
			';
			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds["web"] = 0;
				</script>
			';

			$this->doc->getPageRenderer()->addCssFile('../typo3conf/ext/solr/resources/css/mod_admin/index.css');

				// Render content:
			$this->getModuleContent();
		} else {
				// If no access or if ID == zero
			$docHeaderButtons['save'] = '';
			$this->content .= $this->doc->spacer(10);
		}

			// compile document
		$markers['FUNC_MENU'] = t3lib_BEfunc::getFuncMenu(0, 'SET[function]', $this->MOD_SETTINGS['function'], $this->MOD_MENU['function']);
		$markers['CONTENT'] = $this->content;

				// Build the <body> for the module
		$this->content  = $this->doc->startPage($LANG->getLL('title'));
		$this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
		$this->content  = $this->doc->insertStylesAndJS($this->content);
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void
	 */
	public function printContent() {
		$this->content .= $this->doc->endPage();
		echo $this->content;
	}

	/**
	 * Generates the module content
	 *
	 * @return	void
	 */
	protected function getModuleContent() {
		$pageRoot = (string) $this->MOD_SETTINGS['function'];

			//// TEMPORARY
			// TODO add a "discover/update Solr connections button to the global section"
		$content = '
			<input type="hidden" id="solraction" name="solraction" value="" />

			Site actions:
			<hr style="background: none; border: none; border-bottom: 1px solid #cdcdcd;" />
			<input type="submit" value="Initialize Index Queue" name="s_initializeIndexQueue" onclick="document.forms[0].solraction.value=\'initializeIndexQueue\';" /><br /><br />

			<br />

			Index specific actions:
			<hr style="background: none; border: none; border-bottom: 1px solid #cdcdcd;" />
			<input type="submit" value="Empty Index and Commit" name="s_emptyIndex" onclick="Check = confirm(\'Are you sure?\'); if (Check == true) document.forms[0].solraction.value=\'emptyIndex\';" /><br /><br />
			<input type="submit" value="Commit Pending Documents" name="s_commitPendingDocuments" onclick="document.forms[0].solraction.value=\'commitPendingDocuments\';" /><br /><br />
			<input type="submit" value="Optimize Index" name="s_optimizeIndex" onclick="document.forms[0].solraction.value=\'optimizeIndex\';" /><br /><br />

			<br />
			Delete document(s) from index:<br /><br />
			<label for="delete_uid" style="display:block;width:60px;float:left">Item uid</label>
			<input id="delete_uid" type="text" name="delete_uid" value="" /> (also accepts comma separated lists of uids)<br /><br />
			<label for="delete_type" style="display:block;width:60px;float:left;">Item type</label>
			<input id="delete_type" type="text" name="delete_type" value="" /><br /><br />
			<input type="submit" value="Delete Document(s)"name="s_deleteDocument" onclick="document.forms[0].solraction.value=\'deleteDocument\';" /><br /><br />
		';
			// TODO add a checkbox to the delete documents fields to also remove from Index Queue

		switch($_POST['solraction']) {
			case 'initializeIndexQueue':
				$this->initializeIndexQueue();
				break;
			case 'emptyIndex':
				$this->emptyIndex();
				break;
			case 'optimizeIndex':
				$this->optimizeIndex();
				break;
			case 'commitPendingDocuments':
				$this->commitPendingDocuments();
				break;
			case 'deleteDocument':
				$this->deleteDocument();
				break;
			default:
		}

		$this->content .= $this->doc->section('Apache Solr for TYPO3', $content, FALSE, TRUE);
	}


	//// TEMPORARY

	protected function initializeIndexQueue() {
		$itemIndexQueue = t3lib_div::makeInstance('tx_solr_indexqueue_Queue');
		$itemIndexQueue->initialize($this->site);

			// TODO make dependent on return vale of IQ init
		$flashMessage = t3lib_div::makeInstance(
			't3lib_FlashMessage',
			'Index Queue initialized for site ' . $this->site->getLabel() . '.',
			'',
			t3lib_FlashMessage::OK
		);
		t3lib_FlashMessageQueue::addMessage($flashMessage);
	}

	protected function emptyIndex() {
		$message = 'Index emptied.';
		$severity = t3lib_FlashMessage::OK;

		try {
			$solrServers = $this->connectionManager->getConnectionsBySite($this->site);
			foreach($solrServers as $solrServer) {
					// make sure maybe not-yet committed documents are committed
				$solrServer->commit();
				$solrServer->deleteByQuery('*:*');
				$solrServer->commit();
			}
		} catch (Exception $e) {
			$message = '<p>An error occured while trying to empty the index:</p>'
					 . '<p>' . $e->__toString() . '</p>';
			$severity = t3lib_FlashMessage::ERROR;
		}

		$flashMessage = t3lib_div::makeInstance(
			't3lib_FlashMessage',
			$message,
			'',
			$severity
		);
		t3lib_FlashMessageQueue::addMessage($flashMessage);
	}

	protected function optimizeIndex() {
		$message = 'Index optimized.';
		$severity = t3lib_FlashMessage::OK;

		try {
			$solrServers = $this->connectionManager->getConnectionsBySite($this->site);
			foreach($solrServers as $solrServer) {
				$solrServer->optimize();
			}
		} catch (Exception $e) {
			$message = '<p>An error occured while trying to optimize the index:</p>'
					 . '<p>' . $e->__toString() . '</p>';
			$severity = t3lib_FlashMessage::ERROR;
		}

		$flashMessage = t3lib_div::makeInstance(
			't3lib_FlashMessage',
			$message,
			'',
			$severity
		);
		t3lib_FlashMessageQueue::addMessage($flashMessage);
	}

	protected function commitPendingDocuments() {
		$message = 'Pending documents committed.';
		$severity = t3lib_FlashMessage::OK;

		try {
			$solrServers = $this->connectionManager->getConnectionsBySite($this->site);
			foreach($solrServers as $solrServer) {
				$solrServer->commit();
			}
		} catch (Exception $e) {
			$message = '<p>An error occured while trying to commit:</p>'
					 . '<p>' . $e->__toString() . '</p>';
			$severity = t3lib_FlashMessage::ERROR;
		}

		$flashMessage = t3lib_div::makeInstance(
			't3lib_FlashMessage',
			$message,
			'',
			$severity
		);
		t3lib_FlashMessageQueue::addMessage($flashMessage);
	}

	protected function deleteDocument() {
		$documentUid  = t3lib_div::_POST('delete_uid');
		$documentType = t3lib_div::_POST('delete_type');

		$message  = 'Document(s) with type '. $documentType . ' and id ' . $documentUid . ' deleted';
		$severity = t3lib_FlashMessage::OK;

		try {
			$solrServers = $this->connectionManager->getConnectionsBySite($this->site);
			foreach($solrServers as $solrServer) {
				$uids         = t3lib_div::trimExplode(',', $documentUid);
				$uidCondition = implode(' OR ', $uids);
				$solrServer->deleteByQuery('uid:('. $uidCondition . ') AND type:' . $documentType);

				$solrServer->commit();
			}
		} catch (Exception $e) {
			$message = '<p>An error occured while trying to delete:</p>'
				. '<p>' . $e->__toString() . '</p>';
			$severity = t3lib_FlashMessage::ERROR;
		}

		$flashMessage = t3lib_div::makeInstance(
			't3lib_FlashMessage',
			$message,
			'',
			$severity
		);
		t3lib_FlashMessageQueue::addMessage($flashMessage);
	}

	//// TEMPORARY END


	/**
	 * Create the panel of buttons for submitting the form or otherwise perform operations.
	 *
	 * @return	array	all available buttons as an assoc. array
	 */
	protected function getButtons() {
		$buttons = array();

			// CSH
		$buttons['csh'] = t3lib_BEfunc::cshItem(
			'_MOD_web_func',
			'',
			$GLOBALS['BACK_PATH']
		);

			// SAVE button
		$buttons['save'] = '<input type="image" class="c-inputButton" name="submit" value="Update"'
			. t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/savedok.gif', '')
			. ' title="' . $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:rm.saveDoc', 1)
			. '" />';

			// Shortcut
		if ($GLOBALS['BE_USER']->mayMakeShortcut())	{
			$buttons['shortcut'] = $this->doc->makeShortcutIcon(
				'',
				'function',
				$this->MCONF['name']
			);
		}

		return $buttons;
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/mod_admin/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/mod_admin/index.php']);
}




// Make instance:
$SOBE = t3lib_div::makeInstance('tx_solr_ModuleAdmin');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE) {
	include_once($INC_FILE);
}

$SOBE->main();
$SOBE->printContent();

?>