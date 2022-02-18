<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\Controller\Backend\Search;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection as SolrCoreConnection;
use ApacheSolrForTypo3\Solr\System\Mvc\Backend\Service\ModuleDataStorageService;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * Abstract Module
 */
abstract class AbstractModuleController extends ActionController
{
    /**
     * In the page-tree selected page UID
     *
     * @var int
     */
    protected int $selectedPageUID;

    /**
     * Holds the requested page UID because the selected page uid,
     * might be overwritten by the automatic site selection.
     *
     * @var int
     */
    protected int $requestedPageUID;

    /**
     * @var ?Site
     */
    protected ?Site $selectedSite = null;

    /**
     * @var SiteRepository
     */
    protected SiteRepository $siteRepository;

    /**
     * @var SolrCoreConnection|null
     */
    protected ?SolrCoreConnection $selectedSolrCoreConnection = null;

    /**
     * @var Menu|null
     */
    protected ?Menu $coreSelectorMenu = null;

    /**
     * @var ConnectionManager
     */
    protected ConnectionManager $solrConnectionManager;

    /**
     * @var ModuleDataStorageService
     */
    protected ModuleDataStorageService $moduleDataStorageService;

    /**
     * @var Queue
     */
    protected Queue $indexQueue;

    /**
     * @var SiteFinder
     */
    protected SiteFinder $siteFinder;

    /**
     * @var ModuleTemplateFactory
     */
    protected ModuleTemplateFactory $moduleTemplateFactory;

    /**
     * @var ModuleTemplate
     */
    protected ModuleTemplate $moduleTemplate;

    /**
     * Constructor for dependency injection
     */
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        ModuleDataStorageService $moduleDataStorageService,
        SiteRepository $siteRepository,
        SiteFinder $siteFinder,
        ConnectionManager $solrConnectionManager,
        Queue $indexQueue,
        ?int $selectedPageUID = null
    ) {
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->moduleDataStorageService = $moduleDataStorageService;
        $this->siteRepository = $siteRepository;
        $this->siteFinder = $siteFinder;
        $this->solrConnectionManager = $solrConnectionManager;
        $this->indexQueue = $indexQueue;
        $this->selectedPageUID = $selectedPageUID ?? (int)GeneralUtility::_GP('id');
    }

    /**
     * Injects UriBuilder object.
     *
     * Purpose: Is already set in {@link processRequest} but wanted in PhpUnit
     *
     * @param UriBuilder $uriBuilder
     * @return void
     */
    public function injectUriBuilder(UriBuilder $uriBuilder)
    {
        $this->uriBuilder = $uriBuilder;
    }

    /**
     * @param Site $selectedSite
     */
    public function setSelectedSite(Site $selectedSite)
    {
        $this->selectedSite = $selectedSite;
    }

    /**
     * Initializes the controller and sets needed vars.
     * @throws DBALDriverException
     * @throws Throwable
     * @throws NoSuchArgumentException
     */
    protected function initializeAction()
    {
        parent::initializeAction();
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        if ($this->request->hasArgument('id')) {
            $this->selectedPageUID = (int)$this->request->getArgument('id');
        }

        $this->requestedPageUID = $this->selectedPageUID;

        if ($this->autoSelectFirstSiteAndRootPageWhenOnlyOneSiteIsAvailable()) {
            return;
        }

        if ($this->selectedPageUID < 1) {
            return;
        }

        try {
            $this->selectedSite = $this->siteRepository->getSiteByPageId($this->selectedPageUID);
        } catch (InvalidArgumentException $exception) {
            return;
        }
    }

    /**
     * @return bool
     * @throws DBALDriverException
     * @throws Throwable
     */
    protected function autoSelectFirstSiteAndRootPageWhenOnlyOneSiteIsAvailable(): bool
    {
        $solrConfiguredSites = $this->siteRepository->getAvailableSites();
        $availableSites = $this->siteFinder->getAllSites();
        if (count($solrConfiguredSites) === 1 && count($availableSites) === 1) {
            $this->selectedSite = $this->siteRepository->getFirstAvailableSite();

            // we only overwrite the selected pageUid when no id was passed
            if ($this->selectedPageUID === 0) {
                $this->selectedPageUID = $this->selectedSite->getRootPageId();
            }
            return true;
        }

        return false;
    }

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     * @return void
     * @throws DBALDriverException
     * @throws Throwable
     */
    protected function initializeView($view)
    {
        $sites = $this->siteRepository->getAvailableSites();

        $selectOtherPage = count($sites) > 0 || $this->selectedPageUID < 1;
        $this->view->assign('showSelectOtherPage', $selectOtherPage);
        $this->view->assign('pageUID', $this->selectedPageUID);
        if ($this->selectedPageUID < 1) {
            return;
        }

        $this->moduleTemplate->addJavaScriptCode(
            'mainJsFunctions',
            '
                top.fsMod.recentIds["searchbackend"] = ' . $this->selectedPageUID . ';'
        );
        if (null === $this->selectedSite) {
            return;
        }

        /* @var BackendUserAuthentication $beUser */
        $beUser = $GLOBALS['BE_USER'];
        $permissionClause = $beUser->getPagePermsClause(1);
        $pageRecord = BackendUtility::readPageAccess($this->selectedSite->getRootPageId(), $permissionClause);

        if (false === $pageRecord) {
            throw new InvalidArgumentException(vsprintf('There is something wrong with permissions for page "%s" for backend user "%s".', [$this->selectedSite->getRootPageId(), $beUser->user['username']]), 1496146317);
        }
        $this->moduleTemplate->getDocHeaderComponent()->setMetaInformation($pageRecord);
    }

    /**
     * Generates selector menu in backends doc header using selected page from page tree.
     *
     * @param string|null $uriToRedirectTo
     */
    public function generateCoreSelectorMenuUsingPageTree(string $uriToRedirectTo = null)
    {
        if ($this->selectedPageUID < 1 || null === $this->selectedSite) {
            return;
        }

        $this->generateCoreSelectorMenu($this->selectedSite, $uriToRedirectTo);
    }

    /**
     * Generates Core selector Menu for given Site.
     *
     * @param Site $site
     * @param string|null $uriToRedirectTo
     */
    protected function generateCoreSelectorMenu(Site $site, string $uriToRedirectTo = null)
    {
        $this->coreSelectorMenu = $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->makeMenu();
        $this->coreSelectorMenu->setIdentifier('component_core_selector_menu');

        if (!isset($uriToRedirectTo)) {
            $uriToRedirectTo = $this->uriBuilder->reset()->uriFor();
        }

        $this->initializeSelectedSolrCoreConnection();
        $cores = $this->solrConnectionManager->getConnectionsBySite($site);
        foreach ($cores as $core) {
            $coreAdmin = $core->getAdminService();
            $menuItem = $this->coreSelectorMenu->makeMenuItem();
            $menuItem->setTitle($coreAdmin->getCorePath());
            $uri = $this->uriBuilder->reset()->uriFor(
                'switchCore',
                [
                    'corePath' => $coreAdmin->getCorePath(),
                    'uriToRedirectTo' => $uriToRedirectTo
                ]
            );
            $menuItem->setHref($uri);

            if ($coreAdmin->getCorePath() == $this->selectedSolrCoreConnection->getAdminService()->getCorePath()) {
                $menuItem->setActive(true);
            }
            $this->coreSelectorMenu->addMenuItem($menuItem);
        }

        $this->moduleTemplate->getDocHeaderComponent()->getMenuRegistry()->addMenu($this->coreSelectorMenu);
    }

    /**
     * Empties the Index Queue
     *
     * @return ResponseInterface
     *
     * @noinspection PhpUnused Used in IndexQueue- and IndexAdministration- controllers
     */
    public function clearIndexQueueAction(): ResponseInterface
    {

        $this->indexQueue->deleteItemsBySite($this->selectedSite);
        $this->addFlashMessage(
            LocalizationUtility::translate(
                'solr.backend.index_administration.success.queue_emptied',
                'Solr',
                [$this->selectedSite->getLabel()]
            )
        );

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Switches used core.
     *
     * Note: Does not check availability of core in site. All this stuff is done in the generation step.
     *
     * @param string $corePath
     * @param string $uriToRedirectTo
     *
     * @return ResponseInterface
     *
     * @noinspection PhpUnused Used in IndexQueue- and IndexAdministration- controllers
     */
    public function switchCoreAction(string $corePath, string $uriToRedirectTo): ResponseInterface
    {
        $moduleData = $this->moduleDataStorageService->loadModuleData();
        $moduleData->setCore($corePath);

        $this->moduleDataStorageService->persistModuleData($moduleData);
        $message = LocalizationUtility::translate('coreselector_switched_successfully', 'solr', [$corePath]);
        $this->addFlashMessage($message);
        return new RedirectResponse($uriToRedirectTo, 303);
    }

    /**
     * Returns the Response for be module action.
     *
     * @return ResponseInterface
     */
    protected function getModuleTemplateResponse(): ResponseInterface
    {

        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
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
            return;
        }
        foreach ($solrCoreConnections as $solrCoreConnection) {
            if ($solrCoreConnection->getAdminService()->getCorePath() == $currentSolrCorePath) {
                $this->selectedSolrCoreConnection = $solrCoreConnection;
            }
        }
        if (!$this->selectedSolrCoreConnection instanceof SolrCoreConnection && count($solrCoreConnections) > 0) {
            $this->initializeFirstAvailableSolrCoreConnection($solrCoreConnections, $moduleData);
            $message = LocalizationUtility::translate('coreselector_switched_to_default_core', 'solr', [$currentSolrCorePath, $this->selectedSite->getLabel(), $this->selectedSolrCoreConnection->getAdminService()->getCorePath()]);
            $this->addFlashMessage($message, '', AbstractMessage::NOTICE);
        }
    }

    /**
     * @param SolrCoreConnection[] $solrCoreConnections
     */
    private function initializeFirstAvailableSolrCoreConnection(array $solrCoreConnections, $moduleData)
    {
        if (empty($solrCoreConnections)) {
            return;
        }
        $this->selectedSolrCoreConnection = $solrCoreConnections[0];
        $moduleData->setCore($this->selectedSolrCoreConnection->getAdminService()->getCorePath());
        $this->moduleDataStorageService->persistModuleData($moduleData);
    }
}
