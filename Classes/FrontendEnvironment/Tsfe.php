<?php
namespace ApacheSolrForTypo3\Solr\FrontendEnvironment;

use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationPageResolver;
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
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
use TYPO3\CMS\Core\Http\PropagateResponseException;
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
     * @var SiteFinder
     */
    protected SiteFinder $siteFinder;

    /**
     * Initializes isolated TypoScriptFrontendController for Indexing and backend actions.
     *
     * @param SiteFinder|null $siteFinder
     */
    public function __construct(?SiteFinder $siteFinder = null)
    {
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
    }

    /**
     * Initializes the TSFE for a given page ID and language.
     *
     * @param int $pageId
     * @param int $language
     *
     * @param int|null $rootPageId
     *
     * @throws DBALDriverException
     * @throws Exception\Exception
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     *
     * @todo: Move whole caching stuff from this method and let return TSFE.
     */
    protected function initializeTsfe(int $pageId, int $language = 0, ?int $rootPageId = null)
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

        /* @var Context $context */
        $context = clone (GeneralUtility::makeInstance(Context::class));
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

            try {
                $globalsTSFE->determineId($serverRequest);
            } /** @noinspection PhpRedundantCatchClauseInspection */ catch (PropagateResponseException $exception)
            {
                $this->serverRequestCache[$cacheIdentifier] = null;
                $this->tsfeCache[$cacheIdentifier] = null;
                return;
            }

            $globalsTSFE->tmpl->start($globalsTSFE->rootLine);
            $globalsTSFE->no_cache = false;
            $globalsTSFE->getConfigArray($serverRequest);

            $globalsTSFE->newCObj($serverRequest);
            $globalsTSFE->absRefPrefix = self::getAbsRefPrefixFromTSFE($globalsTSFE);
            $globalsTSFE->calculateLinkVars([]);

            $this->tsfeCache[$cacheIdentifier] = $globalsTSFE;
        }

        // @todo: Not right place for that action, move on more convenient place: indexing a single item+id+lang.
        Locales::setSystemLocaleFromSiteLanguage($siteLanguage);
    }

    /**
     * Returns TypoScriptFrontendController with sand cast context.
     *
     * @param int $pageId
     * @param int $language
     *
     * @param int|null $rootPageId
     *
     * @return TypoScriptFrontendController
     *
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     * @throws DBALDriverException
     * @throws Exception\Exception
     *
     * @todo : Call `$globalsTSFE->newCObj($serverRequest);` each time the TSFE requested. And then remove {@link getServerRequestForTsfeByPageIdAndLanguageId()} method.
     */
    public function getTsfeByPageIdAndLanguageId(int $pageId, int $language = 0, ?int $rootPageId = null): ?TypoScriptFrontendController
    {
        $this->assureIsInitialized($pageId, $language, $rootPageId);
        return $this->tsfeCache[$this->getCacheIdentifier($pageId, $language, $rootPageId)];
    }

    /**
     * Returns TypoScriptFrontendController with sand cast context.
     *
     * @param int $pageId
     * @param int $language
     *
     * @param int|null $rootPageId
     *
     * @return ServerRequest
     *
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     * @throws DBALDriverException
     * @throws Exception\Exception
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
     * @param int $pageId
     * @param int $language
     *
     * @param int|null $rootPageId
     *
     * @throws DBALDriverException
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     * @throws Exception\Exception
     */
    protected function assureIsInitialized(int $pageId, int $language, ?int $rootPageId = null): void
    {
        if(!array_key_exists($this->getCacheIdentifier($pageId, $language, $rootPageId), $this->serverRequestCache)) {
            $this->initializeTsfe($pageId, $language, $rootPageId);
        }
    }

    /**
     * Returns the cache identifier for cached TSFE and ServerRequest objects.
     *
     * @param int $pageId
     * @param int $language
     *
     * @param int|null $rootPageId
     *
     * @return string
     */
    protected function getCacheIdentifier(int $pageId, int $language, ?int $rootPageId = null): string
    {
        return 'root:' . ($rootPageId ?? 'null') . '|page:' . $pageId . '|lang:' . $language;
    }

    /**
     * The TSFE can not be initialized for Spacer and sys-folders.
     * See: "Spacer and sys folders is not accessible in frontend" on {@link \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::getPageAndRootline()}
     *
     * Note: The requested $pidToUse can be one of configured plugin.tx_solr.index.queue.[indexConfig].additionalPageIds.
     *
     * @param int $pidToUse
     *
     * @param int|null $rootPageId
     *
     * @return int
     * @throws DBALDriverException
     * @throws Exception\Exception
     */
    protected function getPidToUseForTsfeInitialization(int $pidToUse, ?int $rootPageId = null): ?int
    {
        // handle plugin.tx_solr.index.queue.[indexConfig].additionalPageIds
        if (isset($rootPageId) && !$this->isRequestedPageAPartOfRequestedSite($pidToUse)) {
            return $rootPageId;
        }
        $pageRecord = BackendUtility::getRecord('pages', $pidToUse);
        $isSpacerOrSysfolder = ($pageRecord['doktype'] ?? null) == PageRepository::DOKTYPE_SPACER || ($pageRecord['doktype'] ?? null) == PageRepository::DOKTYPE_SYSFOLDER;
        if ($isSpacerOrSysfolder === false) {
            return $pidToUse;
        }
        /* @var ConfigurationPageResolver $configurationPageResolve */
        $configurationPageResolver = GeneralUtility::makeInstance(ConfigurationPageResolver::class);
        $pidToUse = $configurationPageResolver->getClosestPageIdWithActiveTemplate($pidToUse);
        if (!isset($pidToUse) && !isset($rootPageId)) {
            throw new Exception\Exception(
                "The closest page with active template to page \"$pidToUse\" could not be resolved and alternative rootPageId is not provided.",
                1637339439
            );
        } else if (isset($rootPageId)) {
            return $rootPageId;
        }
        return $this->getPidToUseForTsfeInitialization($pidToUse, $rootPageId);
    }

    /**
     * Checks if the requested page belongs to site of given root page.
     *
     * @param int $pageId
     * @param int|null $rootPageId
     *
     * @return bool
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
