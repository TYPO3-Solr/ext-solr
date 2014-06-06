<?php
namespace ApacheSolrForTypo3\Solr\Backend\SolrModule;

/***************************************************************
*  Copyright notice
*
*  (c) 2014 Hans Höchtl <hans.hoechtl@typovision.de>
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

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * Manage Synonyms Module
 *
 * @author Hans Höchtl <hans.hoechtl@typovision.de>
 */
class SynonymsModuleController extends AbstractModuleController {

	/**
	 * Module name, used to identify a module f.e. in URL parameters.
	 *
	 * @var string
	 */
	protected $moduleName = 'Synonyms';

	/**
	 * Module title, shows up in the module menu.
	 *
	 * @var string
	 */
	protected $moduleTitle = 'Synonyms';


	/**
	 * Gets synonyms for the currently selected core
	 *
	 * @return void
	 */
	public function indexAction() {
		$solrConnection = $this->getSelectedCoreSolrConnection();

		$synonyms    = array();
		$rawSynonyms = $solrConnection->getSynonyms();

		foreach ($rawSynonyms as $baseWord => $synonymList) {
			$synonyms[$baseWord] = implode(', ', $synonymList);
		}

		$this->view->assign('synonyms', $synonyms);
	}

	/**
	 * Add synonyms to selected core
	 *
	 * @return void
	 */
	public function addSynonymsAction() {
		$solrConnection = $this->getSelectedCoreSolrConnection();
		$synonymMap     = GeneralUtility::_POST('tx_solr_tools_solradministration');

		if (empty($synonymMap['baseWord']) || empty($synonymMap['synonyms'])) {
			$this->addFlashMessage(
				'Please provide a base word and synonyms.',
				'Missing parameter',
				FlashMessage::ERROR
			);
		} else {
			$baseWord = $synonymMap['baseWord'];
			$synonyms = $synonymMap['synonyms'];

			$solrConnection->addSynonym(
				$baseWord,
				GeneralUtility::trimExplode(',', $synonyms, TRUE)
			);

			$this->addFlashMessage(
				'"' . $synonyms . '" added as synonyms for base word "' . $baseWord . '"'
			);
		}

		$this->forward('index');
	}


	/**
	 * Finds the Solr connection to use for the currently selected core.
	 *
	 * @return \Tx_Solr_SolrService Solr connection
	 */
	protected function getSelectedCoreSolrConnection() {
		$currentCoreConnection = NULL;

		$solrConnections = $this->connectionManager->getConnectionsBySite($this->site);
		$currentCore     = $this->moduleDataStorageService->loadModuleData()->getCore();

		foreach ($solrConnections as $solrConnection) {
			if ($solrConnection->getPath() == $currentCore) {
				$currentCoreConnection = $solrConnection;
				break;
			}
		}

		if (is_null($currentCoreConnection)) {
			// when switching sites $currentCore is empty and nothing matched
			$currentCoreConnection = $solrConnections[0];
		}

		return $currentCoreConnection;
	}

}

?>