<?php
namespace ApacheSolrForTypo3\Solr\FrontendEnvironment;

use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationPageResolver;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Context\TypoScriptAspect;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Core\Http\ServerRequest;

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

    /**
     * Initializes the TSFE for a given page ID and language.
     *
     * @param int $pageId
     * @param int $language
     * @throws SiteNotFoundException
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     */
    protected function initializeTsfe(int $pageId, int $language = 0)
    {
        $cacheIdentifier = $this->getCacheIdentifier($pageId, $language);

        // Handle spacer and sys folders, since they are not accessible in frontend, and TSFE can not be fully initialized on them.
        $pidToUse = $this->getPidToUseForTsfeInitialization($pageId);
        if ($pidToUse !== $pageId) {
            $this->initializeTsfe($pidToUse, $language);
            $reusedCacheIdentifier = $this->getCacheIdentifier($pidToUse, $language);
            $this->serverRequestCache[$cacheIdentifier] = $this->serverRequestCache[$reusedCacheIdentifier];
            $this->tsfeCache[$cacheIdentifier] = $this->tsfeCache[$reusedCacheIdentifier];
            return;
        }

        /* @var Context $context */
        $context = clone (GeneralUtility::makeInstance(Context::class));

        /* @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($pageId);
        // $siteLanguage and $languageAspect takes the language id into account.
        //   See: $site->getLanguageById($language);
        //   Therefore the whole TSFE stack is initialized and must be used as is.
        //   Note: ServerRequest, Context, Language, cObj of TSFE MUST NOT be changed or touched in any way,
        //         Otherwise the caching of TSFEs makes no sense anymore.
        //         If you want something to change in TSFE object, please use cloned one!
        $siteLanguage = $site->getLanguageById($language);
        $languageAspect = LanguageAspectFactory::createFromSiteLanguage($siteLanguage);
        $context->setAspect('language', $languageAspect);

        $serverRequest = $this->serverRequestCache[$cacheIdentifier] ?? null;
        if (!isset($this->serverRequestCache[$cacheIdentifier])) {
            $serverRequest = GeneralUtility::makeInstance(ServerRequest::class);
            $this->serverRequestCache[$cacheIdentifier] = $serverRequest =
                $serverRequest->withAttribute('site', $site)
                ->withAttribute('language', $siteLanguage)
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withUri($site->getBase());
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
                    false
                )
            );

            $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
            // for certain situations we need to trick TSFE into granting us
            // access to the page in any case to make getPageAndRootline() work
            // see http://forge.typo3.org/issues/42122
            $pageRecord = BackendUtility::getRecord('pages', $pageId, 'fe_group');
            $userGroups = [0, -1];
            if (!empty($pageRecord['fe_group'])) {
                $userGroups = array_unique(array_merge($userGroups, explode(',', $pageRecord['fe_group'])));
            }
            $context->setAspect('frontend.user', GeneralUtility::makeInstance(UserAspect::class, $feUser, $userGroups));

            /* @var PageArguments $pageArguments */
            $pageArguments = GeneralUtility::makeInstance(PageArguments::class, $pageId, 0, []);

            /* @var TypoScriptFrontendController $globalsTSFE */
            $globalsTSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $context, $site, $siteLanguage, $pageArguments, $feUser);

            // @extensionScannerIgnoreLine
            /** Done in {@link \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::settingLanguage} */
            //$globalsTSFE->sys_page = GeneralUtility::makeInstance(PageRepository::class);

            $template = GeneralUtility::makeInstance(TemplateService::class, $context, null, $globalsTSFE);
            $template->tt_track = false;
            $globalsTSFE->tmpl = $template;
            $context->setAspect('typoscript', GeneralUtility::makeInstance(TypoScriptAspect::class, true));
            $globalsTSFE->no_cache = true;
            $globalsTSFE->determineId($serverRequest);
            $globalsTSFE->tmpl->start($globalsTSFE->rootLine);
            $globalsTSFE->no_cache = false;
            $globalsTSFE->getConfigArray($serverRequest);

            $globalsTSFE->newCObj($serverRequest);
            $globalsTSFE->absRefPrefix = self::getAbsRefPrefixFromTSFE($globalsTSFE);
            $globalsTSFE->calculateLinkVars([]);

            $this->tsfeCache[$cacheIdentifier] = $globalsTSFE;
        }

        // @todo: May be not right place for that action, on indexing a single item+id+lang is it more convenient.
        Locales::setSystemLocaleFromSiteLanguage($siteLanguage);
    }

    /**
     * Returns TypoScriptFrontendController with sand cast context.
     *
     * @throws InternalServerErrorException
     * @throws SiteNotFoundException
     * @throws ServiceUnavailableException
     */
    public function getTsfeByPageIdAndLanguageId(int $pageId, int $language = 0): TypoScriptFrontendController
    {
        $this->assureIsInitialized($pageId, $language);
        return $this->tsfeCache[$this->getCacheIdentifier($pageId, $language)];
    }

    /**
     * Returns TypoScriptFrontendController with sand cast context.
     *
     * @throws InternalServerErrorException
     * @throws SiteNotFoundException
     * @throws ServiceUnavailableException
     */
    public function getServerRequestForTsfeByPageIdAndLanguageId(int $pageId, int $language = 0): ServerRequest
    {
        $this->assureIsInitialized($pageId, $language);
        return $this->serverRequestCache[$this->getCacheIdentifier($pageId, $language)];
    }

    /**
     * Returns TypoScriptFrontendController with sand cast context.
     *
     * @throws InternalServerErrorException
     * @throws SiteNotFoundException
     * @throws ServiceUnavailableException
     */
    public function getContextForTsfeByPageIdAndLanguageId(int $pageId, int $language = 0): ServerRequest
    {
        $this->assureIsInitialized($pageId, $language);
        return $this->serverRequestCache[$this->getCacheIdentifier($pageId, $language)];
    }

    /**
     * Initializes the TSFE, ServerRequest, Context if not already done.
     *
     * @throws InternalServerErrorException
     * @throws SiteNotFoundException
     * @throws ServiceUnavailableException
     */
    protected function assureIsInitialized(int $pageId, int $language): void
    {
        if (!isset($this->serverRequestCache[$this->getCacheIdentifier($pageId, $language)])) {
            $this->initializeTsfe($pageId, $language);
        }
    }

    /**
     * Returns the cache identifier for cached TSFE and ServerRequest objects.
     *
     * @param int $pageId
     * @param int $language
     * @return string
     */
    protected function getCacheIdentifier(int $pageId, int $language): string
    {
        return $pageId . '|' . $language;
    }

    /**
     * The TSFE can not be initialized for Spacer and sys-folders.
     * See: "Spacer and sys folders is not accessible in frontend" on {@link \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::getPageAndRootline()}
     *
     * @param int $pidToUse
     * @return int
     */
    protected function getPidToUseForTsfeInitialization(int $pidToUse): int
    {
        $pageRecord = BackendUtility::getRecord('pages', $pidToUse);
        $isSpacerOrSysfolder = ($pageRecord['doktype'] ?? null) == PageRepository::DOKTYPE_SPACER || ($pageRecord['doktype'] ?? null) == PageRepository::DOKTYPE_SYSFOLDER;
        if ($isSpacerOrSysfolder === false) {
            return $pidToUse;
        }
        /* @var ConfigurationPageResolver $configurationPageResolve */
        $configurationPageResolver = GeneralUtility::makeInstance(ConfigurationPageResolver::class);
        $pidToUse = $configurationPageResolver->getClosestPageIdWithActiveTemplate($pidToUse);
        return $this->getPidToUseForTsfeInitialization($pidToUse);
    }

    /**
     * Resolves the configured absRefPrefix to a valid value and resolved if absRefPrefix
     * is set to "auto".
     */
    private function getAbsRefPrefixFromTSFE(TypoScriptFrontendController $TSFE): string
    {
        $absRefPrefix = '';
        if (empty($TSFE->config['config']['absRefPrefix'])) {
            return $absRefPrefix;
        }

        $absRefPrefix = trim($TSFE->config['config']['absRefPrefix']);
        if ($absRefPrefix === 'auto') {
            $absRefPrefix = GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
        }

        return $absRefPrefix;
    }
}
