<?php
namespace ApacheSolrForTypo3\Solr\Backend\SolrModule;

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

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;


/**
 * Abstract Module
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
abstract class AbstractModuleController extends ActionController implements AdministrationModuleInterface {

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
	 * @var \Tx_Solr_ConnectionManager
	 * @inject
	 */
	protected $connectionManager = NULL;

	/**
	 * The currently selected Site.
	 *
	 * @var \Tx_Solr_Site
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
			$site = $this->request->getArgument('site');

			if (is_numeric($site)) {
				$siteRootPageId = $this->request->getArgument('site');
				$this->site = \Tx_Solr_Site::getSiteByPageId($siteRootPageId);
			} else if ($site instanceof \Tx_Solr_Site) {
				$this->site = $site;
			}
		} catch (NoSuchArgumentException $nsae) {
			$sites = \Tx_Solr_Site::getAvailableSites();

			$site = array_shift($sites);
			$this->site = $site;
		}

		$this->request->setArgument('site', $this->site);

		$moduleData = $this->moduleDataStorageService->loadModuleData();
		$moduleData->setSite($this->site);
		$this->moduleDataStorageService->persistModuleData($moduleData);
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


	/**
	 * Forwards to the index action after resetting module and moduleAction
	 * arguments to prevent execution of module actions.
	 *
	 * @return void
	 */
	protected function forwardToIndex() {
		$requestArguments = $this->request->getArguments();

		foreach ($requestArguments as $argumentName => $_) {
			if (!in_array($argumentName, array('module', 'controller', 'site'))) {
				unset($requestArguments[$argumentName]);
				unset($_GET['tx_solr_tools_solradministration'][$argumentName]);
				unset($this->arguments[$argumentName]);
			}
		}

		$this->request->setArguments($requestArguments);

		$this->forward('index');
	}

	/**
	 * Creates a Message object and adds it to the FlashMessageQueue.
	 *
	 * NOTE: This is a Backport of the 6.2 implementation!
	 *
	 * @param string $messageBody The message
	 * @param string $messageTitle Optional message title
	 * @param integer $severity Optional severity, must be one of \TYPO3\CMS\Core\Messaging\FlashMessage constants
	 * @param boolean $storeInSession Optional, defines whether the message should be stored in the session (default) or not
	 * @return void
	 * @throws \InvalidArgumentException if the message body is no string
	 * @see \TYPO3\CMS\Core\Messaging\FlashMessage
	 */
	public function addFlashMessage($messageBody, $messageTitle = '', $severity = \TYPO3\CMS\Core\Messaging\AbstractMessage::OK, $storeInSession = TRUE) {
		if (version_compare(TYPO3_version, '6.2.0', '>=')) {
			parent::addFlashMessage($messageBody, $messageTitle, $severity, $storeInSession);
		} else {
			if (!is_string($messageBody)) {
				throw new \InvalidArgumentException('The message body must be of type string, "' . gettype($messageBody) . '" given.', 1243258395);
			}
			/* @var FlashMessage $flashMessage */
			$flashMessage = GeneralUtility::makeInstance(
				'TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $messageBody, $messageTitle, $severity, $storeInSession
			);
			$this->controllerContext->getFlashMessageQueue()->enqueue($flashMessage);
		}
	}
}

?>