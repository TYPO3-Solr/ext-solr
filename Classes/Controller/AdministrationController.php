<?php
namespace ApacheSolrForTypo3\Solr\Controller;

/***************************************************************
*  Copyright notice
*
*  (c) 2013-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Model\ModuleData;
use ApacheSolrForTypo3\Solr\Service\ModuleDataStorageService;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Request;
use TYPO3\CMS\Extbase\Mvc\Web\Response;


/**
 * Administration module controller
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class AdministrationController extends ActionController {

	/**
	 * Persistent module data
	 *
	 * @var \ApacheSolrForTypo3\Solr\Domain\Model\ModuleData
	 */
	protected $moduleData = NULL;

	/**
	 * @var \ApacheSolrForTypo3\Solr\Service\ModuleDataStorageService
	 * @inject
	 */
	protected $moduleDataStorageService;

	/**
	 * Administration Module Manager
	 *
	 * @var \ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleManager
	 * @inject
	 */
	protected $moduleManager = NULL;

	/**
	 * Modules
	 *
	 * @var array
	 */
	protected $modules = array();

	/**
	 * Name of the currently active module
	 *
	 * @var string
	 */
	protected $activeModuleName = 'Overview';

	/**
	 * Currently active module
	 *
	 * @var null|\ApacheSolrForTypo3\Solr\Backend\SolrModule\AdministrationModuleInterface|ActionController
	 */
	protected $activeModule = NULL;

	/**
	 * The site to work with
	 *
	 * @var \Tx_Solr_Site
	 */
	protected $site;


	/**
	 * Loads and persists module data
	 *
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @throws \Exception|StopActionException
	 * @return void
	 */
	public function processRequest(RequestInterface $request, ResponseInterface $response) {
		$this->moduleData = $this->moduleDataStorageService->loadModuleData();

		try {
			parent::processRequest($request, $response);
			$this->moduleDataStorageService->persistModuleData($this->moduleData);
		} catch (StopActionException $e) {
			$this->moduleDataStorageService->persistModuleData($this->moduleData);
			throw $e;
		}
	}

	/**
	 * Initializes the controller before invoking an action method.
	 *
	 * @return void
	 */
	protected function initializeAction() {
		// TODO must check whether site exists, and adjust if necessary
		$this->site = $this->moduleData->getSite();

		try {
			$moduleName = $this->request->getArgument('module');
			if ($this->moduleManager->isRegisteredModule($moduleName)) {
				$this->activeModuleName = $moduleName;
				$this->activeModule     = $this->moduleManager->getModule($moduleName);
			}
		} catch (NoSuchArgumentException $e) {
			$this->activeModule = $this->moduleManager->getModule($this->activeModuleName);
		}

		$this->modules = $this->moduleManager->getModules();
	}

	/**
	 * Index Action / Overview
	 *
	 * @return void
	 */
	public function indexAction() {
		$this->view->assign('activeModule', $this->activeModule);
		$this->view->assign('modules', $this->modules);
		$this->view->assign('site', $this->site);

		$this->invokeModuleController();
	}

	/**
	 * Call a sub-module's controller
	 * 
	 */
	protected function invokeModuleController() {
		$activeModuleDescription = $this->moduleManager->getModuleDescription($this->activeModuleName);

		$request = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Mvc\\Web\\Request');
		/* @var Request $request */
		$request->setControllerExtensionName(ucfirst($activeModuleDescription['extensionKey']));
		$request->setControllerName($activeModuleDescription['controller'] . 'Module');
		$request->setControllerActionName('index');
		$request->setArgument('site', $this->site);
		$request->setPluginName($this->request->getPluginName());
		if ($this->request->hasArgument('moduleAction')) {
			// TODO check whether action is registered/allowed
			$request->setControllerActionName($this->request->getArgument('moduleAction'));
		}

		// transfer additional parameters
		foreach ($this->request->getArguments() as $argumentName => $argumentValue) {
			if (in_array($argumentName, array('module', 'moduleAction', 'controller'))) {
				// these have been transferred already
				continue;
			}
			$request->setArgument($argumentName, $argumentValue);
		}

		$response = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Mvc\\Web\\Response');
		/* @var Response $response */

		while (!$request->isDispatched()) {
			try {
				$this->activeModule->processRequest($request, $response);
			} catch (StopActionException $ignoredException) {
			}
		}

		$this->view->assign('moduleContent', $response->getContent());
	}

	/**
	 * Sets the site to work with
	 *
	 * @param integer $site Site root page id
	 * @return void
	 */
	public function setSiteAction($site) {
		$site = \Tx_Solr_Site::getSiteByPageId((int) $site);
		$this->moduleData->setSite($site);
		// when switching the site, reset the core
		$this->moduleData->setCore('');
		$this->moduleDataStorageService->persistModuleData($this->moduleData);

		$this->forwardHome();
	}

	/**
	 * Sets the core to work with.
	 *
	 * @param string $core The core path to use
	 * @param string $module Module to forward to after setting the core
	 * @return void
	 */
	public function setCoreAction($core, $module = 'Overview') {
		$this->moduleData->setCore($core);
		$this->moduleDataStorageService->persistModuleData($this->moduleData);

		$this->forwardToModule($module);
	}

	/**
	 * Forwards to the index action after resetting module and moduleAction
	 * arguments to prevent execution of module actions.
	 *
	 * @return void
	 */
	protected function forwardHome() {
		$requestArguments = $this->request->getArguments();
		unset($requestArguments['module'], $requestArguments['moduleAction']);
		$this->request->setArguments($requestArguments);

		$this->forward('index');
	}

	/**
	 * Forwards to a specific module and module action.
	 *
	 * @param string $module Module name
	 * @param string $moduleAction Module action
	 * @return void
	 */
	protected function forwardToModule($module, $moduleAction = '') {
		$requestArguments = $this->request->getArguments();

		$requestArguments['module'] = $module;
		if (!empty($moduleAction)) {
			$requestArguments['moduleAction'] = $moduleAction;
		} else {
			unset($requestArguments['moduleAction']);
		}

		$this->request->setArguments($requestArguments);

		$this->forward('index');
	}
}

