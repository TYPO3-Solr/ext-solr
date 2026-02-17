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

namespace ApacheSolrForTypo3\Solr\FrontendSimulation;

use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationPageResolver;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
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
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Simulates a frontend environment for backend/CLI contexts.
 *
 * This class creates properly configured ServerRequest objects with all
 * necessary frontend attributes (site, language, typoscript, page information)
 * for use in contexts where frontend capabilities are needed but no actual
 * frontend request exists (e.g., indexing, backend modules, CLI commands).
 */
class FrontendAwareEnvironment implements SingletonInterface
{
    /**
     * @var ServerRequest[]
     */
    protected array $serverRequestCache = [];

    protected SiteFinder $siteFinder;

    public function __construct(?SiteFinder $siteFinder = null)
    {
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
    }

    /**
     * Initializes the simulated frontend environment for a given page ID and language.
     *
     * @throws AspectNotFoundException
     * @throws DBALException
     * @throws Exception\Exception
     * @throws SiteNotFoundException
     * @throws JsonException
     */
    protected function initializeEnvironment(int $pageId, int $language = 0, ?int $rootPageId = null): void
    {
        $cacheIdentifier = $this->getCacheIdentifier($pageId, $language, $rootPageId);

        // Handle spacer and sys-folders, since they are not accessible in frontend.
        // Apart from this, the plugin.tx_solr.index.queue.[indexConfig].additionalPageIds is handled as well.
        $pidToUse = $this->getPidToUseForInitialization($pageId, $rootPageId);
        if ($pidToUse !== $pageId) {
            $this->initializeEnvironment($pidToUse, $language, $rootPageId);
            $reusedCacheIdentifier = $this->getCacheIdentifier($pidToUse, $language, $rootPageId);
            $this->serverRequestCache[$cacheIdentifier] = $this->serverRequestCache[$reusedCacheIdentifier];
            return;
        }

        if (isset($this->serverRequestCache[$cacheIdentifier])) {
            return;
        }

        /** @var Context $context */
        $context = clone GeneralUtility::makeInstance(Context::class);
        $site = $this->siteFinder->getSiteByPageId($pageId);
        $siteLanguage = $site->getLanguageById($language);
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($siteLanguage);
        $context->setAspect('language', $languageAspect);

        $pageInformation = new PageInformation();
        $pageInformation->setId($pageId);
        $pageInformation->setPageRecord(BackendUtility::getRecord('pages', $pageId));
        $pageInformation->setContentFromPid($pageId);

        $rootLine = GeneralUtility::makeInstance(RootlineUtility::class, $pageId)->get();
        $pageInformation->setLocalRootLine($rootLine);

        $pageArguments = GeneralUtility::makeInstance(PageArguments::class, $pageId, '0', []);
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);

        $serverRequest = GeneralUtility::makeInstance(ServerRequest::class)
            ->withAttribute('site', $site)
            ->withAttribute('language', $siteLanguage)
            ->withAttribute('routing', $pageArguments)
            ->withAttribute('frontend.page.information', $pageInformation)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withUri($site->getBase());

        $serverRequest = $serverRequest->withAttribute(
            'frontend.typoscript',
            $configurationManager->getCoreTypoScriptFrontendByRequest($serverRequest),
        );

        // Configure visibility aspect for indexing (hide hidden elements)
        $context->setAspect(
            'visibility',
            GeneralUtility::makeInstance(
                VisibilityAspect::class,
                false,
                false,
            ),
        );

        // Set up frontend user with appropriate access rights
        /** @var FrontendUserAuthentication $feUser */
        $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $pageRecord = BackendUtility::getRecord('pages', $pageId, 'fe_group');
        if (!empty($pageRecord['fe_group'])) {
            $userGroups = explode(',', $pageRecord['fe_group']);
        } else {
            $userGroups = [0, -1];
        }
        $feUser->user = ['uid' => 0, 'username' => '', 'usergroup' => implode(',', $userGroups)];
        $feUser->fetchGroupData($serverRequest);
        $context->setAspect('frontend.user', GeneralUtility::makeInstance(UserAspect::class, $feUser, $userGroups));

        $serverRequest = $serverRequest->withAttribute('frontend.user', $feUser);

        // Attach the fully configured context to the request for later use (e.g., ContentObjectRenderer)
        $serverRequest = $serverRequest->withAttribute('solr.frontend.context', $context);

        $this->serverRequestCache[$cacheIdentifier] = $serverRequest;

        // Set system locale from site language
        Locales::setSystemLocaleFromSiteLanguage($siteLanguage);
    }

    /**
     * Returns a ServerRequest configured for the given page ID and language.
     *
     * The returned request contains all necessary frontend attributes:
     * - 'site' => Site object
     * - 'language' => SiteLanguage object
     * - 'routing' => PageArguments
     * - 'frontend.page.information' => PageInformation
     * - 'frontend.typoscript' => FrontendTypoScript
     * - 'frontend.user' => FrontendUserAuthentication
     *
     * @throws SiteNotFoundException
     * @throws Exception\Exception
     * @throws DBALException
     * @throws JsonException
     * @throws AspectNotFoundException
     */
    public function getServerRequestByPageIdAndLanguageId(int $pageId, int $language = 0, ?int $rootPageId = null): ?ServerRequest
    {
        $this->assureIsInitialized($pageId, $language, $rootPageId);
        return $this->serverRequestCache[$this->getCacheIdentifier($pageId, $language, $rootPageId)] ?? null;
    }

    /**
     * Returns a ServerRequest for the first available language in the fallback chain.
     *
     * Useful for BE-Modules/CLI-Commands where the TypoScript configuration
     * is needed and the specific language doesn't matter.
     *
     * @param int ...$languageFallbackChain
     */
    public function getServerRequestByPageIdAndLanguageFallbackChain(int $pageId, int ...$languageFallbackChain): ?ServerRequest
    {
        foreach ($languageFallbackChain as $languageId) {
            try {
                $request = $this->getServerRequestByPageIdAndLanguageId($pageId, $languageId);
                if ($request instanceof ServerRequest) {
                    return $request;
                }
            } catch (Throwable) {
                continue;
            }
        }
        return null;
    }

    /**
     * Returns a ServerRequest for the first initializable site language.
     *
     * Useful for BE-Modules/CLI-Commands where the TypoScript configuration
     * is needed and the specific language doesn't matter.
     */
    public function getServerRequestByPageIdIgnoringLanguage(int $pageId): ?ServerRequest
    {
        try {
            $typo3Site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (Throwable) {
            return null;
        }
        $availableLanguageIds = array_map(static function ($siteLanguage) {
            return $siteLanguage->getLanguageId();
        }, $typo3Site->getLanguages());

        if (empty($availableLanguageIds)) {
            return null;
        }
        return $this->getServerRequestByPageIdAndLanguageFallbackChain($pageId, ...$availableLanguageIds);
    }

    /**
     * Returns the page ID from a ServerRequest.
     *
     * Convenience method to extract the page ID from the PageInformation attribute.
     */
    public function getPageIdFromRequest(ServerRequest $request): int
    {
        /** @var PageInformation|null $pageInformation */
        $pageInformation = $request->getAttribute('frontend.page.information');
        return $pageInformation?->getId() ?? 0;
    }

    /**
     * Ensures the environment is initialized for the given parameters.
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
        if (!array_key_exists($cacheIdentifier, $this->serverRequestCache)) {
            $this->initializeEnvironment($pageId, $language, $rootPageId);
        }
    }

    /**
     * Returns the cache identifier for cached ServerRequest objects.
     */
    protected function getCacheIdentifier(int $pageId, int $language, ?int $rootPageId = null): string
    {
        return 'root:' . ($rootPageId ?? 'null') . '|page:' . $pageId . '|lang:' . $language;
    }

    /**
     * Determines the appropriate page ID to use for initialization.
     *
     * Spacer and sys-folders are not accessible in frontend context.
     * This method finds a suitable alternative page with an active TypoScript template.
     *
     * @param int $pidToUse The page UID to start searching for a TS template
     * @param int|null $rootPageId The root page UID as fallback
     * @throws AspectNotFoundException
     * @throws Exception\Exception
     * @throws DBALException
     */
    protected function getPidToUseForInitialization(int $pidToUse, ?int $rootPageId = null): ?int
    {
        // Handle plugin.tx_solr.index.queue.[indexConfig].additionalPageIds
        if (isset($rootPageId) && !$this->isRequestedPageAPartOfRequestedSite($pidToUse, $rootPageId)) {
            return $rootPageId;
        }

        $pageRecord = BackendUtility::getRecord('pages', $pidToUse);
        $isSpacerOrSysfolder = ($pageRecord['doktype'] ?? null) == PageRepository::DOKTYPE_SPACER
            || ($pageRecord['doktype'] ?? null) == PageRepository::DOKTYPE_SYSFOLDER;
        if ($isSpacerOrSysfolder === false && $this->isPageAvailable($pageRecord)) {
            return $pidToUse;
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($pidToUse);
        } catch (SiteNotFoundException) {
            return $pidToUse;
        }

        // TYPO3 core also checks site config before sys_template handling
        $siteIsTypoScriptRoot = $site instanceof Site && $site->isTypoScriptRoot();
        if ($siteIsTypoScriptRoot) {
            return $site->getRootPageId();
        }

        // No site configuration found, find the closest page with active template
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
                "Infinite recursion detected while looking for the closest page with active template to page \"$pidToUse\". Please note that the page with active template (usually the root page of the current tree) MUST NOT be a sysfolder.",
                1637339476,
            );
        }

        return $this->getPidToUseForInitialization($pidWithActiveTemplate, $rootPageId);
    }

    /**
     * Checks if the page is available (not hidden, within time constraints).
     *
     * @throws AspectNotFoundException
     */
    protected function isPageAvailable(array $pageRecord): bool
    {
        $currentTime = GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp');
        return $pageRecord['hidden'] === 0 &&
            $pageRecord['starttime'] <= $currentTime &&
            ($pageRecord['endtime'] === 0 || $pageRecord['endtime'] > 0 && $pageRecord['endtime'] > $currentTime);
    }

    /**
     * Checks if the requested page belongs to the site of the given root page.
     */
    protected function isRequestedPageAPartOfRequestedSite(int $pageId, ?int $rootPageId = null): bool
    {
        if (!isset($rootPageId)) {
            return false;
        }
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException) {
            return false;
        }
        return $rootPageId === $site->getRootPageId();
    }
}
