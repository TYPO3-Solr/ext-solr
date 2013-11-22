<?php
namespace ApacheSolrForTypo3\Solr\Backend\SolrModule;

/***************************************************************
*  Copyright notice
*
*  (c) 2013 Ingo Renner <ingo@typo3.org>
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

use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;


/**
 * Abstract Module
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
abstract class AbstractModule extends ActionController implements AdministrationModuleInterface {

	/**
	 * Module name, used to identify a module f.e. in URL parameters.
	 *
	 * @var string
	 */
	protected $moduleName = '';

	/**
	 * Module title, shows up in the module menu.
	 *
	 * @var string
	 */
	protected $moduleTitle = '';

	/**
	 * @var \ApacheSolrForTypo3\Solr\Service\ModuleDataStorageService
	 * @inject
	 */
	protected $moduleDataStorageService;

	/**
	 * Extension key
	 *
	 * @var string
	 */
	protected $extensionKey = '';

	/**
	 * @var \tx_solr_ConnectionManager
	 * @inject
	 */
	protected $connectionManager = NULL;

	/**
	 * The currently selected Site.
	 *
	 * @var \tx_solr_Site
	 */
	protected $site;


	/**
	 * Gets the module name.
	 *
	 * @return string Module name
	 */
	public function getName() {
		return $this->moduleName;
	}

	/**
	 * Gets the module title.
	 *
	 * @return string Module title
	 */
	public function getTitle() {
		return $this->moduleTitle;
	}

	/**
	 * Sets the extension key
	 *
	 * @param string $extensionKey Extension key
	 */
	public function setExtensionKey($extensionKey) {
		$this->extensionKey = $extensionKey;
	}

	/**
	 * Gets the extension key
	 *
	 * @return string Extension key
	 */
	public function getExtensionKey() {
		return $this->extensionKey;
	}

	/**
	 * Initializes resources commonly needed for several actions
	 *
	 * @return void
	 */
	protected function initializeAction() {
		try {
			$this->site = $this->request->getArgument('site');
		} catch (NoSuchArgumentException $nsae) {
			$sites = \tx_solr_Site::getAvailableSites();

			$site = array_shift($sites);
			$this->site = $site;
			$this->request->setArgument('site', $site);


			$moduleData = $this->moduleDataStorageService->loadModuleData();
			$moduleData->setSite($site);
			$this->moduleDataStorageService->persistModuleData($moduleData);
		}
	}

	/**
	 * Initializes the view before invoking an action method.
	 *
	 * Assigns the current module to the view
	 *
	 * @param \TYPO3\CMS\Extbase\Mvc\View\ViewInterface $view The view to be initialized
	 * @return void
	 */
	protected function initializeView(ViewInterface $view) {
		$view->assign('module', $this);
	}

}

?>