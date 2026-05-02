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
use TYPO3\CMS\Backend\Context\PageContext;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\Components\Menu\Menu;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Schema\Capability\TcaSchemaCapability;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\View\ViewInterface as FluidStandaloneViewInterface;

/**
 * Abstract Module
 */
abstract class AbstractModuleController extends ActionController
{
    private const INDEX_ADMINISTRATION_LANGUAGE_DOMAIN = 'solr.modules.index_admin';

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
        protected readonly ComponentFactory $componentFactory,
        protected readonly IconFactory $iconFactory,
        protected readonly BackendUriBuilder $backendUriBuilder,
        protected readonly ModuleDataStorageService $moduleDataStorageService,
        protected readonly SiteRepository $siteRepository,
        protected readonly SiteFinder $siteFinder,
        protected readonly ConnectionManager $solrConnectionManager,
        protected readonly TcaSchemaFactory $tcaSchemaFactory,
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
        } elseif ($this->request->hasArgument('selectedPageUID')) {
            $this->selectedPageUID = (int)$this->request->getArgument('selectedPageUID');
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
        $availableSites = $this->siteFinder->getAllSites();
        if (count($availableSites) === 1 && $this->siteRepository->hasExactlyOneAvailableSite()) {
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
     * @throws InvalidArgumentException
     * @throws UnexpectedTYPO3SiteInitializationException
     */
    protected function initializeView(ViewInterface|FluidStandaloneViewInterface $view): void
    {
        $selectOtherPage = $this->siteRepository->hasAvailableSites() || $this->selectedPageUID < 1;
        $this->moduleTemplate->assign('showSelectOtherPage', $selectOtherPage);
        $this->moduleTemplate->assign('selectedPageUID', $this->selectedPageUID);
        if ($this->selectedPageUID < 1) {
            return;
        }

        if ($this->selectedSite === null) {
            return;
        }

        /** @var BackendUserAuthentication $beUser */
        $beUser = $GLOBALS['BE_USER'];
        $pageContext = $this->request->getAttribute('pageContext');
        $pageRecord = null;
        $rootLine = [];
        $languageId = 0;
        $pageUid = $this->selectedPageUID;

        if ($pageContext instanceof PageContext && $pageContext->isAccessible()) {
            $pageRecord = $pageContext->pageRecord;
            $rootLine = $pageContext->rootLine;
            $languageId = $pageContext->getPrimaryLanguageId();
            $pageUid = $pageContext->pageId;
        }

        if ($pageRecord === null) {
            $permissionClause = $beUser->getPagePermsClause(1);
            $pageRecord = BackendUtility::readPageAccess($pageUid, $permissionClause);
            if ($pageRecord === false && $pageUid !== $this->selectedSite->getRootPageId()) {
                $pageUid = $this->selectedSite->getRootPageId();
                $pageRecord = BackendUtility::readPageAccess($pageUid, $permissionClause);
            }
            if ($pageRecord !== false) {
                $rootLine = BackendUtility::BEgetRootLine((int)$pageRecord['uid'], $permissionClause);
            }
        }

        if ($pageRecord === false) {
            throw new InvalidArgumentException(vsprintf('There is something wrong with permissions for page "%s" for backend user "%s".', [$this->selectedSite->getRootPageId(), $beUser->user['username']]), 1496146317);
        }

        $this->moduleTemplate->getDocHeaderComponent()->setPageBreadcrumb($pageRecord);
        $this->addPageActionButtons($pageRecord, $rootLine, $pageUid, $languageId, $pageContext instanceof PageContext ? $pageContext : null);
    }

    private function addPageActionButtons(array $pageRecord, array $rootLine, int $pageUid, int $languageId, ?PageContext $pageContext): void
    {
        $previewUriBuilder = PreviewUriBuilder::create($pageRecord);
        $this->moduleTemplate->addButtonToButtonBar(
            $this->componentFactory->createViewButton(
                $previewUriBuilder
                    ->withRootLine($rootLine)
                    ->withLanguage($languageId)
                    ->buildDispatcherDataAttributes() ?? [],
            ),
            ButtonBar::BUTTON_POSITION_LEFT,
            15,
        );

        if (!$this->isPageEditable($pageRecord, $languageId)) {
            return;
        }

        $editablePageUid = $this->getEditablePageUid($pageContext, $pageUid, $languageId);
        $editParams = [
            'edit' => ['pages' => [$editablePageUid => 'edit']],
            'module' => $this->getCurrentBackendRouteIdentifier(),
            'returnUrl' => $this->getCurrentRequestUri(),
        ];
        $editPagePropertiesLabel = $this->getLanguageService()->sL('LLL:EXT:backend/Resources/Private/Language/locallang_layout.xlf:editPageProperties')
            ?: 'Edit page properties';

        $editButton = $this->componentFactory->createGenericButton()
            ->setTag('typo3-backend-contextual-record-edit-trigger')
            ->setAttributes([
                'url' => (string)$this->backendUriBuilder->buildUriFromRoute('record_edit_contextual', $editParams),
                'edit-url' => (string)$this->backendUriBuilder->buildUriFromRoute('record_edit', $editParams),
            ])
            ->setLabel($editPagePropertiesLabel)
            ->setShowLabelText(true)
            ->setIcon($this->iconFactory->getIcon('actions-page-open', IconSize::SMALL));

        $this->moduleTemplate->addButtonToButtonBar($editButton, ButtonBar::BUTTON_POSITION_LEFT, 20);
    }

    private function getEditablePageUid(?PageContext $pageContext, int $pageUid, int $languageId): int
    {
        if ($pageContext instanceof PageContext && $languageId > 0 && ($overlayRecord = $pageContext->languageInformation->getTranslationRecord($languageId)) !== null) {
            return (int)$overlayRecord['uid'];
        }

        return $pageUid;
    }

    private function isPageEditable(array $pageRecord, int $languageId): bool
    {
        if ($pageRecord === []) {
            return false;
        }

        $schema = $this->tcaSchemaFactory->get('pages');
        if ($schema->hasCapability(TcaSchemaCapability::AccessReadOnly)) {
            return false;
        }

        /** @var BackendUserAuthentication $beUser */
        $beUser = $GLOBALS['BE_USER'];
        if ($beUser->isAdmin()) {
            return true;
        }

        if ($schema->hasCapability(TcaSchemaCapability::AccessAdminOnly)) {
            return false;
        }

        $isEditLocked = false;
        if ($schema->hasCapability(TcaSchemaCapability::EditLock)) {
            $isEditLocked = $pageRecord[$schema->getCapability(TcaSchemaCapability::EditLock)->getFieldName()] ?? false;
        }
        if ($isEditLocked) {
            return false;
        }

        return $beUser->doesUserHaveAccess($pageRecord, Permission::PAGE_EDIT)
            && $beUser->checkLanguageAccess($languageId)
            && $beUser->check('tables_modify', 'pages');
    }

    private function getCurrentBackendRouteIdentifier(): string
    {
        $route = $this->request->getAttribute('route');
        if (is_object($route) && method_exists($route, 'getOption')) {
            $identifier = $route->getOption('_identifier');
            if (is_string($identifier) && $identifier !== '') {
                return $identifier;
            }
        }

        return 'searchbackend';
    }

    private function getCurrentRequestUri(): string
    {
        $normalizedParams = $this->request->getAttribute('normalizedParams');
        if ($normalizedParams instanceof NormalizedParams) {
            return $normalizedParams->getRequestUri();
        }

        return $this->request->getRequestTarget();
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
                ],
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
     * @noinspection PhpUnused Used in IndexQueue- and IndexAdministration- controllers
     */
    public function clearIndexQueueAction(): ResponseInterface
    {
        $this->indexQueue->deleteItemsBySite($this->selectedSite);
        $this->addFlashMessage(
            LocalizationUtility::translate(
                'flash.queueEmptied',
                self::INDEX_ADMINISTRATION_LANGUAGE_DOMAIN,
                ['site' => $this->selectedSite->getLabel()],
            ),
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

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
