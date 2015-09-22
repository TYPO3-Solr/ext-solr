<?php
namespace ApacheSolrForTypo3\Solr\Backend\SolrModule;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Ingo Renner <ingo@typo3.org>
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
 * Manage stop word resources
 *
 */
class StopWordsModuleController extends AbstractModuleController {

	/**
	 * Module name, used to identify a module f.e. in URL parameters.
	 *
	 * @var string
	 */
	protected $moduleName = 'StopWords';

	/**
	 * Module title, shows up in the module menu.
	 *
	 * @var string
	 */
	protected $moduleTitle = 'Stop Words';


	/**
	 * Lists stop words for the currently selected core
	 *
	 * @return void
	 */
	public function indexAction() {
		$solrConnection = $this->getSelectedCoreSolrConnection();

		$stopWords = $solrConnection->getStopWords();
		$this->view->assign('stopWords', $stopWords);
	}

	public function saveStopWordsAction() {
		$solrConnection = $this->getSelectedCoreSolrConnection();
		$stopWords = GeneralUtility::_POST('tx_solr_tools_solradministration');

		$this->forwardToIndex();
	}
}
