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
use ApacheSolrForTypo3\Solr\Domain\Site\Exception\UnexpectedTYPO3SiteInitializationException;
use ApacheSolrForTypo3\Solr\Domain\Site\Site;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\Exception\InvalidArgumentException;
use ApacheSolrForTypo3\Solr\IndexQueue\QueueInterface;
use ApacheSolrForTypo3\Solr\System\Mvc\Backend\Service\ModuleDataStorageService;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection as SolrCoreConnection;
use Doctrine\DBAL\Exception as DBALException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * Abstract Module
 */
abstract class AbstractModuleController extends ActionController
{
    /**
     * Holds the requested page UID because the selected page uid,
     * might be overwritten by the automatic site selection.
     */
    protected int $requestedPageUID;

    protected ?Site $selectedSite = null;

    protected ?SolrCoreConnection $selectedSolrCoreConnection = null;

    protected ?Menu $coreSelectorMenu = null;

    protected ModuleTemplate $moduleTemplate;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly IconFactory $iconFactory,
        protected readonly ModuleDataStorageService $moduleDataStorageService,
        protected readonly SiteRepository $siteRepository,
        protected readonly SiteFinder $siteFinder,
        protected readonly ConnectionManager $solrConnectionManager,
        protected QueueInterface $indexQueue,
        protected ?int $selectedPageUID = null,
    ) {
        $this->selectedPageUID = $selectedPageUID ?? 0;
    }

    /**
     * Injects UriBuilder object.
     * Purpose: Is already set in {@link processRequest} but wanted in PhpUnit
     */
    public function injectUriBuilder(UriBuilder $uriBuilder): void
    {
        $this->uriBuilder = $uriBuilder;
    }

    public function setSelectedSite(Site $selectedSite): void
    {
        $this->selectedSite = $selectedSite;
    }

    /**
     * Initializes the controller and sets needed vars.
     *
     * @throws UnexpectedTYPO3SiteInitializationException
     * @throws DBALException
     */
    protected function initializeAction(): void
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
        } catch (InvalidArgumentException) {
            return;
        }
    }

    /**
     * Tries to select single available site's root page
     *
     * @throws UnexpectedTYPO3SiteInitializationException
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
     * @throws UnexpectedTYPO3SiteInitializationException
     * @param ViewInterface $view
     */
    protected function initializeView($view): void
    {
        $sites = $this->siteRepository->getAvailableSites();

        $selectOtherPage = count($sites) > 0 || $this->selectedPageUID < 1;
        $this->moduleTemplate->assign('showSelectOtherPage', $selectOtherPage);
        $this->moduleTemplate->assign('pageUID', $this->selectedPageUID);
        if ($this->selectedPageUID < 1) {
            return;
        }

        if ($this->selectedSite === null) {
            return;
        }

        /** @var BackendUserAuthentication $beUser */
        $beUser = $GLOBALS['BE_USER'];
        $permissionClause = $beUser->getPagePermsClause(1);
        $pageRecord = BackendUtility::readPageAccess($this->selectedSite->getRootPageId(), $permissionClause);

        if ($pageRecord === false) {
            throw new InvalidArgumentException(vsprintf('There is something wrong with permissions for page "%s" for backend user "%s".', [$this->selectedSite->getRootPageId(), $beUser->user['username']]), 1496146317);
        }
        $this->moduleTemplate->getDocHeaderComponent()->setMetaInformation($pageRecord);
    }

    /**
     * Generates selector menu in backends doc header using selected page from page tree.
     */
    public function generateCoreSelectorMenuUsingPageTree(?string $uriToRedirectTo = null): void
    {
        if ($this->selectedPageUID < 1 || $this->selectedSite === null) {
            return;
        }

        $this->generateCoreSelectorMenu($this->selectedSite, $uriToRedirectTo);
    }

    /**
     * Generates Core selector Menu for given Site.
     */
    protected function generateCoreSelectorMenu(Site $site, ?string $uriToRedirectTo = null): void
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
                    'uriToRedirectTo' => $uriToRedirectTo,
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
     * @throws DBALException
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
     * Note: Does not check availability of core in site. All this stuff is done in the generation step.
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
     * Initializes the solr core connection considerately to the components state.
     * Uses and persists default core connection if persisted core in Site does not exist.
     */
    private function initializeSelectedSolrCoreConnection(): void
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
            $this->addFlashMessage($message, '', ContextualFeedbackSeverity::NOTICE);
        }
    }

    /**
     * @param SolrCoreConnection[] $solrCoreConnections
     */
    private function initializeFirstAvailableSolrCoreConnection(array $solrCoreConnections, $moduleData): void
    {
        if (empty($solrCoreConnections)) {
            return;
        }
        $this->selectedSolrCoreConnection = array_shift($solrCoreConnections);
        $moduleData->setCore($this->selectedSolrCoreConnection->getAdminService()->getCorePath());
        $this->moduleDataStorageService->persistModuleData($moduleData);
    }
}
