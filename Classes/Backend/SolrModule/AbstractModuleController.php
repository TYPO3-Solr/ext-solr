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

use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\Utility\StringUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Abstract Module
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
abstract class AbstractModuleController extends ActionController implements AdministrationModuleInterface
{

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
     * @var \ApacheSolrForTypo3\Solr\ConnectionManager
     * @inject
     */
    protected $connectionManager = null;

    /**
     * The currently selected Site.
     *
     * @var Site
     */
    protected $site;

    /**
     * @var \ApacheSolrForTypo3\Solr\Utility\StringUtility
     */
    protected $stringUtility;

    /**
     * Gets the module name.
     *
     * @return string Module name
     */
    public function getName()
    {
        return $this->moduleName;
    }

    /**
     * Gets the module title.
     *
     * @return string Module title
     */
    public function getTitle()
    {
        return $this->moduleTitle;
    }

    /**
     * Sets the extension key
     *
     * @param string $extensionKey Extension key
     */
    public function setExtensionKey($extensionKey)
    {
        $this->extensionKey = $extensionKey;
    }

    /**
     * Gets the extension key
     *
     * @return string Extension key
     */
    public function getExtensionKey()
    {
        return $this->extensionKey;
    }

    /**
     * Method to pass a StringUtil object.
     * Use to overwrite injected object in unit test context.
     *
     * @param \ApacheSolrForTypo3\Solr\Utility\StringUtility $stringUtility
     */
    public function injectStringHelper(StringUtility $stringUtility)
    {
        $this->stringUtility = $stringUtility;
    }

    /**
     * Initializes resources commonly needed for several actions
     *
     * @return void
     */
    protected function initializeAction()
    {
        try {
            $site = $this->request->getArgument('site');

            if (is_numeric($site)) {
                $siteRootPageId = $this->request->getArgument('site');
                $this->site = Site::getSiteByPageId($siteRootPageId);
            } else {
                if ($site instanceof Site) {
                    $this->site = $site;
                }
            }
        } catch (NoSuchArgumentException $nsae) {
            $sites = Site::getAvailableSites();

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
    protected function initializeView(ViewInterface $view)
    {
        $view->assign('module', $this);
    }


    /**
     * Checks if the request argument is present an returns it. If not it returns the default value.
     *
     * @param string $argumentKey
     * @param mixed $default
     * @return mixed
     */
    protected function getRequestArgumentOrDefaultValue($argumentKey, $default)
    {
        // no request -> return default
        if (! isset($this->request)) {
            return $default;
        }

        // argument not present -> return default value
        if (! $this->request->hasArgument($argumentKey)) {
            return $default;
        }

        return $this->request->getArgument($argumentKey);
    }

    /**
     * Forwards to the index action after resetting module and moduleAction
     * arguments to prevent execution of module actions.
     *
     * @return void
     */
    protected function forwardToIndex()
    {
        $requestArguments = $this->request->getArguments();

        foreach ($requestArguments as $argumentName => $_) {
            if (!in_array($argumentName,
                array('module', 'controller', 'site'))
            ) {
                unset($requestArguments[$argumentName]);
                unset($_GET['tx_solr_tools_solradministration'][$argumentName]);
                unset($this->arguments[$argumentName]);
            }
        }

        $this->request->setArguments($requestArguments);

        $this->forward('index');
    }

    /**
     * Finds the Solr connection to use for the currently selected core.
     *
     * @return \ApacheSolrForTypo3\Solr\SolrService Solr connection
     */
    protected function getSelectedCoreSolrConnection()
    {
        $currentCoreConnection = null;

        $solrConnections = $this->connectionManager->getConnectionsBySite($this->site);
        $currentCore = $this->moduleDataStorageService->loadModuleData()->getCore();

        foreach ($solrConnections as $solrConnection) {
            if ($solrConnection->getPath() == $currentCore) {
                $currentCoreConnection = $solrConnection;
                break;
            }
        }

        if (is_null($currentCoreConnection)) {
            // when switching sites $currentCore is empty and nothing matched
            // fall back to the connection's first core
            $currentCoreConnection = $solrConnections[0];
        }

        return $currentCoreConnection;
    }

    /**
     * Adds flash massages from another flash message queue, e.g. solr.queue.initializer
     *
     * @param string $identifier
     * @return void
     */
    protected function addFlashMessagesByQueueIdentifier($identifier)
    {
        $flashMessages = $this->controllerContext->getFlashMessageQueue($identifier)->getAllMessages();
        foreach ($flashMessages as $message) {
            $this->addFlashMessage($message->getMessage(), $message->getTitle, $message->getSeverity());
        }
    }
}
