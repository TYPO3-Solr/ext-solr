<?php
namespace ApacheSolrForTypo3\Solr\Controller\Backend\Search;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 dkd Internet Service GmbH <solr-support@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Site;
use ApacheSolrForTypo3\Solr\SolrService as SolrCoreConnection;
use ApacheSolrForTypo3\Solr\System\Mvc\Backend\Component\Exception\InvalidViewObjectNameException;
use ApacheSolrForTypo3\Solr\Utility\StringUtility;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\NotFoundView;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Abstract Module
 *
 * @property BackendTemplateView $view
 */
abstract class AbstractModuleController extends ActionController
{
    /**
     * Backend Template Container
     *
     * @var string
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

    /**
     * @var \ApacheSolrForTypo3\Solr\Utility\StringUtility
     */
    protected $stringUtility;

    /**
     * In the pagetree selected page UID
     *
     * @var int
     */
    protected $selectedPageUID;

    /**
     * @var SiteRepository
     */
    protected $siteRepository;
    /**
     * @var Site
     */
    protected $selectedSite;

    /**
     * @var SolrCoreConnection
     */
    protected $selectedSolrCoreConnection;

    /**
     * @var Menu
     */
    protected $coreSelectorMenu = null;

    /**
     * @var \ApacheSolrForTypo3\Solr\ConnectionManager
     * @inject
     */
    protected $solrConnectionManager = null;

    /**
     * @var \ApacheSolrForTypo3\Solr\System\Mvc\Backend\Service\ModuleDataStorageService
     * @inject
     */
    protected $moduleDataStorageService = null;

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
     * Initializes the controller and sets needed vars.
     */
    protected function initializeAction()
    {
        parent::initializeAction();
        $this->selectedPageUID = (int)GeneralUtility::_GP('id');
        if ($this->request->hasArgument('id')) {
            $this->selectedPageUID = (int)$this->request->getArgument('id');
        }

        if ($this->selectedPageUID < 1) {
            return;
        }

        $this->siteRepository = $this->objectManager->get(SiteRepository::class);
        $this->selectedSite = $this->siteRepository->getSiteByPageId($this->selectedPageUID);
    }

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);
        if ($view instanceof NotFoundView || $this->selectedPageUID < 1) {
            return;
        }
        /* @var BackendUserAuthentication $beUser */
        $beUser = $GLOBALS['BE_USER'];
        $permissionClause = $beUser->getPagePermsClause(1);
        $pageRecord = BackendUtility::readPageAccess($this->selectedSite->getRootPageId(), $permissionClause);

        if (false === $pageRecord) {
            throw new \InvalidArgumentException(vsprintf('There is something wrong with permissions for page "%s" for backend user "%s".', [$this->selectedSite->getRootPageId(), $beUser->user['username']]), 1496146317);
        }
        $this->view->getModuleTemplate()->getDocHeaderComponent()->setMetaInformation($pageRecord);

        $this->view->getModuleTemplate()->addJavaScriptCode('mainJsFunctions', '
                top.fsMod.recentIds["searchbackend"] = ' . (int)$this->selectedPageUID . ';'
        );
    }

    /**
     * Generates selector menu in backends doc header using selected page from page tree.
     *
     * @param string|null $uriToRedirectTo
     */
    public function generateCoreSelectorMenuUsingPageTree(string $uriToRedirectTo = null)
    {
        if ($this->selectedPageUID < 1) {
            return;
        }

        if ($this->view instanceof NotFoundView) {
            $this->initializeSelectedSolrCoreConnection();
            return;
        }

        $this->generateCoreSelectorMenu($this->selectedSite, $uriToRedirectTo);
    }

    /**
     * Generates Core selector Menu for given Site.
     *
     * @param Site $site
     * @param string|null $uriToRedirectTo
     * @throws InvalidViewObjectNameException
     */
    protected function generateCoreSelectorMenu(Site $site, string $uriToRedirectTo = null)
    {
        if (!$this->view instanceof BackendTemplateView) {
            throw new InvalidViewObjectNameException(vsprintf(
                'The controller "%s" must use BackendTemplateView to be able to generate menu for backends docheader. \
                Please set `protected $defaultViewObjectName = BackendTemplateView::class;` field in your controller.',
                [static::class]), 1493804179);
        }
        $this->view->getModuleTemplate()->setFlashMessageQueue($this->controllerContext->getFlashMessageQueue());

        $this->coreSelectorMenu = $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $this->coreSelectorMenu->setIdentifier('component_core_selector_menu');

        if (!isset($uriToRedirectTo)) {
            $uriToRedirectTo = $this->uriBuilder->reset()->uriFor();
        }

        $this->initializeSelectedSolrCoreConnection();
        $cores = $this->solrConnectionManager->getConnectionsBySite($site);
        foreach ($cores as $core) {
            $menuItem = $this->coreSelectorMenu->makeMenuItem();
            $menuItem->setTitle($core->getPath());
            $uri = $this->uriBuilder->reset()->uriFor('switchCore',
                [
                    'corePath' => $core->getPath(),
                    'uriToRedirectTo' => $uriToRedirectTo
                ]
            );
            $menuItem->setHref($uri);

            if ($core->getPath() == $this->selectedSolrCoreConnection->getPath()) {
                $menuItem->setActive(true);
            }
            $this->coreSelectorMenu->addMenuItem($menuItem);
        }

        $this->view->getModuleTemplate()->getDocHeaderComponent()->getMenuRegistry()->addMenu($this->coreSelectorMenu);
    }

    /**
     * Switches used core.
     *
     * Note: Does not check availability of core in site. All this stuff is done in the generation step.
     *
     * @param string $corePath
     * @param string $uriToRedirectTo
     */
    public function switchCoreAction(string $corePath, string $uriToRedirectTo)
    {
        $moduleData = $this->moduleDataStorageService->loadModuleData();
        $moduleData->setCore($corePath);

        $this->moduleDataStorageService->persistModuleData($moduleData);
        $message = LocalizationUtility::translate('coreselector_switched_successfully', 'solr', [$corePath]);
        $this->addFlashMessage($message);
        $this->redirectToUri($uriToRedirectTo);
    }

    /**
     * Initializes the solr core connection considerately to the components state.
     * Uses and persists default core connection if persisted core in Site does not exist.
     *
     */
    private function initializeSelectedSolrCoreConnection()
    {
        $moduleData = $this->moduleDataStorageService->loadModuleData();

        $solrCoreConnections = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);
        $currentSolrCorePath = $moduleData->getCore();
        if (empty($currentSolrCorePath)) {
            $this->initializeFirstAvailableSolrCoreConnection($solrCoreConnections, $moduleData);
        }
        foreach ($solrCoreConnections as $solrCoreConnection) {
            if ($solrCoreConnection->getPath() == $currentSolrCorePath) {
                $this->selectedSolrCoreConnection = $solrCoreConnection;
            }
        }
        if (!$this->selectedSolrCoreConnection instanceof SolrCoreConnection && count($solrCoreConnections) > 0) {
            $this->initializeFirstAvailableSolrCoreConnection($solrCoreConnections, $moduleData);
            $message = LocalizationUtility::translate('coreselector_switched_to_default_core', 'solr', [$currentSolrCorePath, $this->selectedSite->getLabel(), $this->selectedSolrCoreConnection->getPath()]);
            $this->addFlashMessage($message, '', AbstractMessage::NOTICE);
        }
    }

    /**
     * @param SolrCoreConnection[] $solrCoreConnections
     */
    private function initializeFirstAvailableSolrCoreConnection(array $solrCoreConnections, $moduleData)
    {
        $this->selectedSolrCoreConnection = $solrCoreConnections[0];
        $moduleData->setCore($this->selectedSolrCoreConnection->getPath());
        $this->moduleDataStorageService->persistModuleData($moduleData);
    }
}
