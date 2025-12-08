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

namespace ApacheSolrForTypo3\Solr\FrontendEnvironment;

use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationPageResolver;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use ReflectionClass;
use Throwable;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Class Tsfe is a factory class for TSFE(TypoScriptFrontendController) objects.
 */
class Tsfe implements SingletonInterface
{
    /**
     * @var TypoScriptFrontendController[]
     */
    protected array $tsfeCache = [];

    /**
     * @var ServerRequest[]
     */
    protected array $serverRequestCache = [];

    protected SiteFinder $siteFinder;

    /**
     * Initializes isolated TypoScriptFrontendController for Indexing and backend actions.
     */
    public function __construct(?SiteFinder $siteFinder = null)
    {
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
    }

    /**
     * Initializes the TSFE for a given page ID and language.
     *
     * @throws AspectNotFoundException
     * @throws DBALException
     * @throws Exception\Exception
     * @throws SiteNotFoundException
     * @throws JsonException
     *
     * @todo: Move whole caching stuff from this method and let return TSFE.
     */
    protected function initializeTsfe(int $pageId, int $language = 0, ?int $rootPageId = null): void
    {
        $cacheIdentifier = $this->getCacheIdentifier($pageId, $language, $rootPageId);

        // Handle spacer and sys-folders, since they are not accessible in frontend, and TSFE can not be fully initialized on them.
        // Apart from this, the plugin.tx_solr.index.queue.[indexConfig].additionalPageIds is handled as well.
        $pidToUse = $this->getPidToUseForTsfeInitialization($pageId, $rootPageId);
        if ($pidToUse !== $pageId) {
            $this->initializeTsfe($pidToUse, $language, $rootPageId);
            $reusedCacheIdentifier = $this->getCacheIdentifier($pidToUse, $language, $rootPageId);
            $this->serverRequestCache[$cacheIdentifier] = $this->serverRequestCache[$reusedCacheIdentifier];
            $this->tsfeCache[$cacheIdentifier] = $this->tsfeCache[$reusedCacheIdentifier];
            //            if ($rootPageId === null) {
            //                // @Todo: Resolve and set TSFE object for $rootPageId.
            //            }
            return;
        }

        /** @var Context $context */
        $context = clone GeneralUtility::makeInstance(Context::class);
        $site = $this->siteFinder->getSiteByPageId($pageId);
        // $siteLanguage and $languageAspect takes the language id into account.
        //   See: $site->getLanguageById($language);
        //   Therefore the whole TSFE stack is initialized and must be used as is.
        //   Note: ServerRequest, Context, Language, cObj of TSFE MUST NOT be changed or touched in any way,
        //         Otherwise the caching of TSFEs makes no sense anymore.
        //         If you want something to change in TSFE object, please use cloned one!
        $siteLanguage = $site->getLanguageById($language);
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($siteLanguage);
        $context->setAspect('language', $languageAspect);

        $pageInformation = new PageInformation();
        $pageInformation->setId($pageId);
        $pageInformation->setPageRecord(BackendUtility::getRecord('pages', $pageId));
        $pageInformation->setContentFromPid($pageId);

        $rootLine = GeneralUtility::makeInstance(RootlineUtility::class, $pageId)->get();
        $pageInformation->setLocalRootLine($rootLine);

        $serverRequest = $this->serverRequestCache[$cacheIdentifier] ?? null;
        $pageArguments = GeneralUtility::makeInstance(PageArguments::class, $pageId, '0', []);
        if (!isset($this->serverRequestCache[$cacheIdentifier])) {
            $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);

            $serverRequest = GeneralUtility::makeInstance(ServerRequest::class)
                ->withAttribute('site', $site)
                ->withAttribute('language', $siteLanguage)
                ->withAttribute('routing', $pageArguments)
                ->withAttribute('frontend.page.information', $pageInformation)
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withUri($site->getBase());

            $this->serverRequestCache[$cacheIdentifier] = $serverRequest = $serverRequest->withAttribute(
                'frontend.typoscript',
                $configurationManager->getCoreTypoScriptFrontendByRequest($serverRequest),
            );
        }

        if (!isset($this->tsfeCache[$cacheIdentifier])) {
            // TYPO3 by default enables a preview mode if a backend user is logged in,
            // the VisibilityAspect is configured to show hidden elements.
            // Due to this setting hidden relations/translations might be indexed
            // when running the Solr indexer via the TYPO3 backend.
            // To avoid this, the VisibilityAspect is adapted for indexing.
            $context->setAspect(
                'visibility',
                GeneralUtility::makeInstance(
                    VisibilityAspect::class,
                    false,
                    false,
                ),
            );

            /** @var FrontendUserAuthentication $feUser */
            $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
            // for certain situations we need to trick TSFE into granting us
            // access to the page in any case to make getPageAndRootline() work
            // see http://forge.typo3.org/issues/42122
            $pageRecord = BackendUtility::getRecord('pages', $pageId, 'fe_group');
            if (!empty($pageRecord['fe_group'])) {
                $userGroups = explode(',', $pageRecord['fe_group']);
            } else {
                $userGroups = [0, -1];
            }
            $feUser->user = ['uid' => 0, 'username' => '', 'usergroup' => implode(',', $userGroups) ];
            $feUser->fetchGroupData($serverRequest);
            $context->setAspect('frontend.user', GeneralUtility::makeInstance(UserAspect::class, $feUser, $userGroups));

            $serverRequest = $serverRequest->withAttribute('frontend.user', $feUser);
            /** @var TypoScriptFrontendController $tsfe */
            $tsfe = GeneralUtility::makeInstance(TypoScriptFrontendController::class);
            $this->setCoreContextOnTsfeObjectAndDependencies(
                $tsfe,
                $context,
            );
            $tsfe->id = $pageId;
            $tsfe->newCObj($serverRequest);

            $this->serverRequestCache[$cacheIdentifier] = $serverRequest;
            $this->tsfeCache[$cacheIdentifier] = $tsfe;
        }

        // @todo: Not right place for that action, move on more convenient place: indexing a single item+id+lang.
        Locales::setSystemLocaleFromSiteLanguage($siteLanguage);
    }

    /**
     * Returns TypoScriptFrontendController with sand cast context.
     *
     * @throws SiteNotFoundException
     * @throws Exception\Exception
     * @throws DBALException
     * @throws JsonException
     * @throws AspectNotFoundException
     */
    public function getTsfeByPageIdAndLanguageId(int $pageId, int $language = 0, ?int $rootPageId = null): ?TypoScriptFrontendController
    {
        $this->assureIsInitialized($pageId, $language, $rootPageId);
        return $this->tsfeCache[$this->getCacheIdentifier($pageId, $language, $rootPageId)];
    }

    /**
     * Returns TypoScriptFrontendController for first available language id in fallback chain.
     *
     * Is usable for BE-Modules/CLI-Commands stack only, where the rendered TypoScript configuration
     * of EXT:solr* stack is wanted and the language id does not matter.
     *
     * NOTE: This method MUST NOT be used on indexing context.
     *
     * @param int ...$languageFallbackChain
     */
    public function getTsfeByPageIdAndLanguageFallbackChain(int $pageId, int ...$languageFallbackChain): ?TypoScriptFrontendController
    {
        foreach ($languageFallbackChain as $languageId) {
            try {
                $tsfe = $this->getTsfeByPageIdAndLanguageId($pageId, $languageId);
                if ($tsfe instanceof TypoScriptFrontendController) {
                    return $tsfe;
                }
            } catch (Throwable $e) {
                // no needs to log or do anything, the method MUST not return anything if it can't.
                continue;
            }
        }
        return null;
    }

    /**
     * Returns TSFE for first initializable site language.
     *
     * Is usable for BE-Modules/CLI-Commands stack only, where the rendered TypoScript configuration
     * of EXT:solr* stack is wanted and the language id does not matter.
     */
    public function getTsfeByPageIdIgnoringLanguage(int $pageId): ?TypoScriptFrontendController
    {
        try {
            $typo3Site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (Throwable $e) {
            return null;
        }
        $availableLanguageIds = array_map(static function ($siteLanguage) {
            return $siteLanguage->getLanguageId();
        }, $typo3Site->getLanguages());

        if (empty($availableLanguageIds)) {
            return null;
        }
        return $this->getTsfeByPageIdAndLanguageFallbackChain($pageId, ...$availableLanguageIds);
    }

    /**
     * Returns TypoScriptFrontendController with sand cast context.
     *
     * @throws SiteNotFoundException
     * @throws Exception\Exception
     * @throws DBALException
     * @throws JsonException
     * @throws AspectNotFoundException
     *
     * @noinspection PhpUnused
     */
    public function getServerRequestForTsfeByPageIdAndLanguageId(int $pageId, int $language = 0, ?int $rootPageId = null): ?ServerRequest
    {
        $this->assureIsInitialized($pageId, $language, $rootPageId);
        return $this->serverRequestCache[$this->getCacheIdentifier($pageId, $language, $rootPageId)];
    }

    /**
     * Initializes the TSFE, ServerRequest, Context if not already done.
     *
     *
     *
     * @throws SiteNotFoundException
     * @throws Exception\Exception
     * @throws DBALException
     * @throws JsonException
     * @throws AspectNotFoundException
     */
    protected function assureIsInitialized(int $pageId, int $language, ?int $rootPageId = null): void
    {
        $cacheIdentifier = $this->getCacheIdentifier($pageId, $language, $rootPageId);
        if (!array_key_exists($cacheIdentifier, $this->tsfeCache)) {
            $this->initializeTsfe($pageId, $language, $rootPageId);
        }
    }

    /**
     * Returns the cache identifier for cached TSFE and ServerRequest objects.
     */
    protected function getCacheIdentifier(int $pageId, int $language, ?int $rootPageId = null): string
    {
        return 'root:' . ($rootPageId ?? 'null') . '|page:' . $pageId . '|lang:' . $language;
    }

    /**
     * The TSFE can not be initialized for Spacer and sys-folders.
     * See: "Spacer and sys folders is not accessible in frontend" on {@link TypoScriptFrontendController::getPageAndRootline}
     *
     * Note: The requested $pidToUse can be one of configured plugin.tx_solr.index.queue.[indexConfig].additionalPageIds.
     *
     * @param int $pidToUse The page UID to start searching for a TS template
     * @param ?int $rootPageId The root page UID is a fallback to detect TS template. That's the case, if $pidToUse is outside the current tree.
     * @throws AspectNotFoundException
     * @throws Exception\Exception
     * @throws DBALException
     */
    protected function getPidToUseForTsfeInitialization(int $pidToUse, ?int $rootPageId = null): ?int
    {
        // handle plugin.tx_solr.index.queue.[indexConfig].additionalPageIds
        if (isset($rootPageId) && !$this->isRequestedPageAPartOfRequestedSite($pidToUse, $rootPageId)) {
            return $rootPageId;
        }

        $pageRecord = BackendUtility::getRecord('pages', $pidToUse);
        $isSpacerOrSysfolder = ($pageRecord['doktype'] ?? null) == PageRepository::DOKTYPE_SPACER || ($pageRecord['doktype'] ?? null) == PageRepository::DOKTYPE_SYSFOLDER;
        if ($isSpacerOrSysfolder === false && $this->isPageAvailableForTSFE($pageRecord)) {
            return $pidToUse;
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($pidToUse);
        } catch (SiteNotFoundException $e) {
            return $pidToUse;
        }

        // Copy&Paste from SysTemplateTreeBuilder::getTreeBySysTemplateRowsAndSite()
        // TYPO3 core also checks site config before sys_template handling
        $siteIsTypoScriptRoot = $site instanceof Site && $site->isTypoScriptRoot();
        if ($siteIsTypoScriptRoot) {
            return $site->getRootPageId();
        }

        // No site configuration found, so we need to find the closest page with active template
        /** @var ConfigurationPageResolver $configurationPageResolver */
        $configurationPageResolver = GeneralUtility::makeInstance(ConfigurationPageResolver::class);
        $pidWithActiveTemplate = $configurationPageResolver->getClosestPageIdWithActiveTemplate($pidToUse);
        if (!isset($pidWithActiveTemplate) && !isset($rootPageId)) {
            throw new Exception\Exception(
                "The closest page with active template to page \"$pidToUse\" could not be resolved and alternative rootPageId is not provided.",
                1637339439,
            );
        }

        // Check for recursion that can happen if the root page is a sysfolder with a typoscript template
        if ($pidWithActiveTemplate === $pidToUse && $site->getRootPageId() === $rootPageId) {
            throw new Exception\Exception(
                "Infinite recursion detected while looking for the closest page with active template to page \"$pidToUse\" . Please note that the page with active template (usually the root page of the current tree) MUST NOT be a sysfolder.",
                1637339476,
            );
        }

        return $this->getPidToUseForTsfeInitialization($pidWithActiveTemplate, $rootPageId);
    }

    /**
     * Checks if the page is available for TSFE.
     *
     * @param array $pageRecord
     * @return bool
     * @throws AspectNotFoundException
     */
    protected function isPageAvailableForTSFE(array $pageRecord): bool
    {
        $currentTime = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        return $pageRecord['hidden'] === 0 &&
            $pageRecord['starttime'] <= $currentTime &&
            ($pageRecord['endtime'] === 0 || $pageRecord['endtime'] > 0 && $pageRecord['endtime'] > $currentTime)
        ;
    }

    /**
     * Checks if the requested page belongs to site of given root page.
     */
    protected function isRequestedPageAPartOfRequestedSite(int $pageId, ?int $rootPageId = null): bool
    {
        if (!isset($rootPageId)) {
            return false;
        }
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException $e) {
            return false;
        }
        return $rootPageId === $site->getRootPageId();
    }

    protected function setCoreContextOnTsfeObjectAndDependencies(
        TypoScriptFrontendController $tsfe,
        Context $context,
    ): void {
        $tsfeReflection = new ReflectionClass($tsfe);
        $tsfeReflectionContextProperty = $tsfeReflection->getProperty('context');
        $tsfeReflectionContextProperty->setValue($tsfe, $context);
        $tsfe->sys_page = GeneralUtility::makeInstance(
            PageRepository::class,
            $context,
        );
    }
}
