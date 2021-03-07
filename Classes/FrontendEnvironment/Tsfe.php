<?php
namespace ApacheSolrForTypo3\Solr\FrontendEnvironment;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Context\TypoScriptAspect;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Core\Http\ServerRequest;

class Tsfe implements SingletonInterface
{

    private $tsfeCache = [];

    private $requestCache = [];

    /**
     * @param int $pageId
     * @param int $language
     * @throws AspectNotFoundException
     */
    public function changeLanguageContext(int $pageId, int $language): void
    {
        /* @var Context $context */
        $context = GeneralUtility::makeInstance(Context::class);
        if ($context->hasAspect('language')) {
            $hasRightLanguageId = $context->getPropertyFromAspect('language', 'id') === $language;
            $hasRightContentLanguageId = $context->getPropertyFromAspect('language', 'contentId')  === $language;
            if ($hasRightLanguageId && $hasRightContentLanguageId) {
                return;
            }
        }

        /* @var $siteFinder SiteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        try {
            $site = $siteFinder->getSiteByPageId($pageId);
            $languageAspect = LanguageAspectFactory::createFromSiteLanguage($site->getLanguageById($language));
            $context->setAspect('language', $languageAspect);
        } catch (SiteNotFoundException $e) {

        }
    }

    /**
     * Initializes the TSFE for a given page ID and language.
     *
     * @param $pageId
     * @param int $language
     * @throws AspectNotFoundException
     * @throws ImmediateResponseException
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws SiteNotFoundException
     */
    public function initializeTsfe($pageId, $language = 0)
    {

        // resetting, a TSFE instance with data from a different page Id could be set already
        unset($GLOBALS['TSFE']);

        $cacheId = $pageId . '|' . $language;

        /** @var Context $context */
        $context = GeneralUtility::makeInstance(Context::class);
        $this->changeLanguageContext((int)$pageId, (int)$language);

        /* @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($pageId);
        $siteLanguage = $site->getLanguageById($language);

        if (!isset($this->requestCache[$cacheId])) {
            /* @var ServerRequest $request */
            $request = GeneralUtility::makeInstance(ServerRequest::class);
            $request = $request->withAttribute('site', $site);
            $this->requestCache[$cacheId] = $request->withAttribute('language', $siteLanguage);
        }
        $GLOBALS['TYPO3_REQUEST'] = $this->requestCache[$cacheId];


        if (!isset($this->tsfeCache[$cacheId])) {

            /* @var TypoScriptFrontendController $globalsTSFE */
            $globalsTSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $context, $site, $siteLanguage);
            $GLOBALS['TSFE'] = $globalsTSFE;
            $GLOBALS['TSFE']->id = $pageId;
            $GLOBALS['TSFE']->type = 0;

            // for certain situations we need to trick TSFE into granting us
            // access to the page in any case to make getPageAndRootline() work
            // see http://forge.typo3.org/issues/42122
            $pageRecord = BackendUtility::getRecord('pages', $pageId, 'fe_group');

            $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
            $userGroups = [0, -1];
            if (!empty($pageRecord['fe_group'])) {
                $userGroups = array_unique(array_merge($userGroups, explode(',', $pageRecord['fe_group'])));
            }
            $context->setAspect('frontend.user', GeneralUtility::makeInstance(UserAspect::class, $feUser, $userGroups));

            // @extensionScannerIgnoreLine
            $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
            $GLOBALS['TSFE']->getPageAndRootlineWithDomain($pageId, $GLOBALS['TYPO3_REQUEST']);

            $template = GeneralUtility::makeInstance(TemplateService::class, $context);
            $GLOBALS['TSFE']->tmpl = $template;
            $context->setAspect('typoscript', GeneralUtility::makeInstance(TypoScriptAspect::class, true));
            $GLOBALS['TSFE']->no_cache = true;
            $GLOBALS['TSFE']->tmpl->start($GLOBALS['TSFE']->rootLine);
            $GLOBALS['TSFE']->no_cache = false;
            $GLOBALS['TSFE']->getConfigArray();
            $GLOBALS['TSFE']->settingLanguage();

            $GLOBALS['TSFE']->newCObj();
            $GLOBALS['TSFE']->absRefPrefix = self::getAbsRefPrefixFromTSFE($GLOBALS['TSFE']);
            $GLOBALS['TSFE']->calculateLinkVars([]);

            $this->tsfeCache[$cacheId] = $GLOBALS['TSFE'];
        }

        $GLOBALS['TSFE'] = $this->tsfeCache[$cacheId];
        Locales::setSystemLocaleFromSiteLanguage($siteLanguage);
        $this->changeLanguageContext((int)$pageId, (int)$language);
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
