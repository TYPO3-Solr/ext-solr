<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo@typo3.org>
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
	 * @var tx_solr_Site
	 */
	protected $site = NULL;

	/**
	 * @var tx_solr_ConnectionManager
	 */
	protected $connectionManager = NULL;

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

		if ($rootPageId) {
			$this->site              = t3lib_div::makeInstance('tx_solr_Site', $rootPageId);
			$this->connectionManager = t3lib_div::makeInstance('tx_solr_ConnectionManager');
		}

		$docHeaderButtons = $this->getButtons();

		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id)) {

				// Draw the form
			$this->doc->form = '<form action="" method="post" name="editform" enctype="multipart/form-data">';

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
			if ($this->site) {
				$this->getModuleContent();
			} else {
				$this->getModuleContentNoSiteConfigured();
			}

		} else {
				// If no access or if ID == zero
			$docHeaderButtons['save'] = '';
			$this->content .= $this->doc->spacer(10);
		}

			// compile document
		$markers['FUNC_MENU'] = $this->getFunctionMenu();
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
	 * @return void
	 */
	protected function getModuleContent() {
			//// TEMPORARY
			// TODO add a "discover/update Solr connections button to the global section"

		$content = '
			<input type="hidden" id="solraction" name="solraction" value="" />
			';

		$content .= '<fieldset><legend>Site Actions</legend>';
		$content .= $this->renderIndexQueueInitializationSelector();
		$content .= '
				<input type="submit" value="Initialize Index Queue" name="s_initializeIndexQueue" onclick="document.forms[0].solraction.value=\'initializeIndexQueue\';" /> ';
		$content .= t3lib_BEfunc::wrapInHelp('', '', '', array(
			'title'       => 'Index Queue Initialization',
			'description' => 'Initializing the Index Queue is the most complete way to force reindexing, or to build the Index Queue for
							 the first time. The Index Queue Worker scheduler task will then index the items listed in the Index Queue.
							 Initializing the Index Queue without selecting specific indexing configurations will behave like selecting all.'
		));

		$content .= '
				<br /><br /><hr /><br />
				<input type="submit" value="Clean up Site Index" name="s_cleanupSiteCores" onclick="document.forms[0].solraction.value=\'cleanupSiteCores\';" />';

		$content .= '
				<br /><br />
				<input type="submit" value="Empty Site Index" name="s_deleteSiteDocuments" onclick="Check = confirm(\'This will commit documents which may be pending, delete documents belonging to the currently selected site and commit again afterwards. Are you sure you want to delete the site\\\'s documents?\'); if (Check == true) document.forms[0].solraction.value=\'deleteSiteDocuments\';" />
			';

		$content .= '
				<br /><br />
				<input type="submit" value="Reload Index Configuration" name="s_reloadCore" onclick="document.forms[0].solraction.value=\'reloadSiteCores\';" />';


		$content .= '
			<br /><br /><hr /><br />
			<p>
				Delete document(s) from site index<br /><br />
			</p>
			<label for="delete_uid" style="display:block;width:60px;float:left">Item uid</label>
			<input id="delete_uid" type="text" name="delete_uid" value="" /> (also accepts comma separated lists of uids)<br /><br />
			<label for="delete_type" style="display:block;width:60px;float:left;">Item type</label>
			<input id="delete_type" type="text" name="delete_type" value="" /> (table name)<br /><br />
			<input type="submit" value="Delete Document(s)"name="s_deleteDocument" onclick="document.forms[0].solraction.value=\'deleteDocument\';" /><br /><br />
			';

		$content .= '</fieldset>';

		$content .= '
			<fieldset>
				<legend>Global Actions (affecting all sites and indexes)</legend>
				<input type="submit" value="Empty Index" name="s_emptyIndex" onclick="Check = confirm(\'This will commit documents which may be pending, clear the index and commit again afterwards. Are you sure you want to empty the index?\'); if (Check == true) document.forms[0].solraction.value=\'emptyIndex\';" /><br /><br />
				<input type="submit" value="Commit Pending Documents" name="s_commitPendingDocuments" onclick="document.forms[0].solraction.value=\'commitPendingDocuments\';" /><br /><br />
				<input type="submit" value="Optimize Index" name="s_optimizeIndex" onclick="document.forms[0].solraction.value=\'optimizeIndex\';" /><br /><br />
			</fieldset>';

		$content .= '
			<hr class="double" />
			API Key: ' . tx_solr_Api::getApiKey();
			// TODO add a checkbox to the delete documents fields to also remove from Index Queue

		switch($_POST['solraction']) {
			case 'initializeIndexQueue':
				$this->initializeIndexQueue();
				break;
			case 'cleanupSiteCores':
				$this->cleanupSiteIndex();
				break;
			case 'deleteSiteDocuments':
				$this->deleteSiteDocuments();
				break;
			case 'reloadSiteCores':
				$this->reloadSiteCores();
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

	/**
	 * Renders a field to select which indexing configurations to initialize.
	 *
	 * Uses TCEforms.
	 *
	 *  @return string Markup for the select field
	 */
	protected function renderIndexQueueInitializationSelector() {
		$tceForm = t3lib_div::makeInstance('t3lib_TCEforms');

		$tablesToIndex = $this->getIndexQueueConfigurationTableMap();

		$PA = array(
			'fieldChangeFunc' => array(),
			'itemFormElName' => 'tx_solr-index-queue-initialization'
		);

		$icon = 'tcarecords-' . $tableName . '-default';

		$formField = $tceForm->getSingleField_typeSelect_checkbox(
			'', // table
			'', // field
			'', // row
			$PA, // array with additional configuration options
			array(), // config,
			$this->buildSelectorItems($tablesToIndex), // items
			'' // Label for no-matching-value
		);

			// need to wrap the field in a TCEforms table to make the CSS apply
		$form = '
		<table class="typo3-TCEforms tx_solr-TCEforms">
			<tr>
				<td>' . "\n" . $formField . "\n" . '</td>
			</tr>
		</table>
		';

		return $form;
	}

	/**
	 * Builds a map of indexing configuration names to tables to to index.
	 *
	 * @return array Indexing configuration to database table map
	 */
	protected function getIndexQueueConfigurationTableMap() {
		$indexingTableMap = array();

		$solrConfiguration = tx_solr_Util::getSolrConfigurationFromPageId($this->site->getRootPageId());

		foreach ($solrConfiguration['index.']['queue.'] as $name => $configuration) {
			if (is_array($configuration)) {
				$name = substr($name, 0, -1);

				if ($solrConfiguration['index.']['queue.'][$name]) {
					$table = $name;
					if ($solrConfiguration['index.']['queue.'][$name . '.']['table']) {
						$table = $solrConfiguration['index.']['queue.'][$name . '.']['table'];
					}

					$indexingTableMap[$name] = $table;
				}
			}
		}

		return $indexingTableMap;
	}

	/**
	 * Builds the items to render in the TCEforms select field.
	 *
	 * @param array $tablesToIndex A map of indexing configuration to database tables
	 * @return array Selectable items for the TCEforms select field
	 */
	protected function buildSelectorItems(array $tablesToIndex) {
		$selectorItems = array();

		foreach ($tablesToIndex as $configurationName => $tableName) {
			$icon = 'tcarecords-' . $tableName . '-default';
			if ($tableName == 'pages') {
				$icon = 'apps-pagetree-page-default';
			}

			$labelTableName = '';
			if ($configurationName != $tableName) {
				$labelTableName = ' (' . $tableName . ')';
			}

			$selectorItems[] = array(
				$configurationName . $labelTableName,
				$configurationName,
				$icon
			);
		}

		return $selectorItems;
	}

	protected function getModuleContentNoSiteConfigured() {
		# TODO add button to init Solr connections
		$this->content = 'No sites configured for Solr yet.';
	}

	protected function getFunctionMenu() {
		$functionMenu = 'No sites configured for Solr yet.';

		if ($this->site) {
			$functionMenu = t3lib_BEfunc::getFuncMenu(
				0,
				'SET[function]',
				$this->MOD_SETTINGS['function'],
				$this->MOD_MENU['function']
			);
		}

		return $functionMenu;
	}

	//// TEMPORARY

	protected function initializeIndexQueue() {
		$initializedIndexingConfigurations = array();

		$itemIndexQueue = t3lib_div::makeInstance('tx_solr_indexqueue_Queue');

		$indexingConfigurationsToInitialize = t3lib_div::_POST('tx_solr-index-queue-initialization');
		if (!empty($indexingConfigurationsToInitialize)) {
				// initialize selected indexing configuration only
			foreach ($indexingConfigurationsToInitialize as $indexingConfigurationName) {
				$initializedIndexingConfiguration = $itemIndexQueue->initialize(
					$this->site,
					$indexingConfigurationName
				);

					// track initialized indexing configurations for the flash message
				$initializedIndexingConfigurations = array_merge(
					$initializedIndexingConfigurations,
					$initializedIndexingConfiguration
				);
			}
		} else {
				// nothing selected specifically, initialize the complete queue
			$initializedIndexingConfigurations = $itemIndexQueue->initialize($this->site);
		}

			// TODO make status dependent on return vale of IQ init
		$flashMessage = t3lib_div::makeInstance(
			't3lib_FlashMessage',
			'Initialized indexing configurations: ' . implode(', ', array_keys($initializedIndexingConfigurations)),
			'Index Queue initialized',
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
				$solrServer->commit(FALSE, FALSE, FALSE);
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

	protected function cleanupSiteIndex() {
		$garbageCollector = t3lib_div::makeInstance('tx_solr_garbageCollector');
		$garbageCollector->cleanIndex($this->site);

		$flashMessage = t3lib_div::makeInstance(
			't3lib_FlashMessage',
			'Index cleaned up.',
			'',
			t3lib_FlashMessage::OK
		);
		t3lib_FlashMessageQueue::addMessage($flashMessage);
	}

	protected function deleteSiteDocuments() {
		$siteHash = $this->site->getSiteHash();
		$message  = 'Documents deleted.';
		$severity = t3lib_FlashMessage::OK;

		try {
			$solrServers = $this->connectionManager->getConnectionsBySite($this->site);
			foreach($solrServers as $solrServer) {
				// make sure maybe not-yet committed documents are committed
				$solrServer->commit();
				$solrServer->deleteByQuery('siteHash:' . $siteHash);
				$solrServer->commit(FALSE, FALSE, FALSE);
			}
		} catch (Exception $e) {
			$message = '<p>An error occured while trying to delete documents from the index:</p>'
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

	protected function reloadSiteCores() {
		$coresReloaded = TRUE;
		$solrServers = $this->connectionManager->getConnectionsBySite($this->site);

		foreach($solrServers as $solrServer) {
			/* @var $solrServer tx_solr_SolrService */

			$path = $solrServer->getPath();
			$pathElements = explode('/', trim($path, '/'));

			$coreName = array_pop($pathElements);

			$coreAdminReloadUrl =
				$solrServer->getScheme() . '://' .
				$solrServer->getHost() . ':' .
				$solrServer->getPort() . '/' .
				$pathElements[0] . '/' .
				'admin/cores?action=reload&core=' .
				$coreName;

			$httpTransport = $solrServer->getHttpTransport();
			$httpResponse  = $httpTransport->performGetRequest($coreAdminReloadUrl);
			$solrResponse  = new Apache_Solr_Response($httpResponse, $solrServer->getCreateDocuments(), $solrServer->getCollapseSingleValueArrays());

			if ($solrResponse->getHttpStatus() != 200) {
				$coresReloaded = FALSE;

				$flashMessage = t3lib_div::makeInstance(
					't3lib_FlashMessage',
					'Failed to reload index configuration for core "' . $coreName . '"',
					'',
					t3lib_FlashMessage::ERROR
				);
				t3lib_FlashMessageQueue::addMessage($flashMessage);
			}
		}

		if ($coresReloaded) {
			$flashMessage = t3lib_div::makeInstance(
				't3lib_FlashMessage',
				'Index configuration reloaded.',
				'',
				t3lib_FlashMessage::OK
			);
			t3lib_FlashMessageQueue::addMessage($flashMessage);
		}
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
				$solrServer->commit(FALSE, FALSE, FALSE);
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

		if (empty($documentUid) || empty($documentType)) {
			$message  = 'Missing uid or type to delete documents.';
			$severity = t3lib_FlashMessage::ERROR;
		} else {
			try {
				$uids         = t3lib_div::trimExplode(',', $documentUid);
				$uidCondition = implode(' OR ', $uids);

				$solrServers = $this->connectionManager->getConnectionsBySite($this->site);
				foreach($solrServers as $solrServer) {
					$response = $solrServer->deleteByQuery(
						'uid:(' . $uidCondition . ')'
						.' AND type:' . $documentType
						.' AND siteHash:' . $this->site->getSiteHash()
					);
					$solrServer->commit(FALSE, FALSE, FALSE);

					if ($response->getHttpStatus() != 200) {
						throw new RuntimeException('Delete Query failed.', 1332250835);
					}
				}
			} catch (Exception $e) {
				$message  = $e->getMessage();
				$severity = t3lib_FlashMessage::ERROR;
			}
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