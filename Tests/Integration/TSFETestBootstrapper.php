<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use ApacheSolrForTypo3\Solr\Util;
use Composer\Autoload\ClassLoader;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Utility\EidUtility;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Http\RequestHandler;

/**
 * Class TSFETestBootstrapper
 * @package ApacheSolrForTypo3\Solr\Tests\Integration
 */
class TSFETestBootstrapper
{
    /**
     * @deprecated this code can be dropped when TYPO3 9 support will be dropped
     * @return TSFEBootstrapResult
     */
    public function legacyBootstrap($pageId = 1, $MP = '', $language = 0)
    {
        $result = new TSFEBootstrapResult();

        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $request = GeneralUtility::makeInstance(ServerRequest::class);

        $site = null;
        $siteLanguage = null;
        if ($pageId !== null) {
            try {
                $site = $siteFinder->getSiteByPageId($pageId);
                $siteLanguage = $site->getLanguageById($language);
                $request = $request->withAttribute('site', $site);
                $request = $request->withAttribute('language', $siteLanguage);
            } catch (SiteNotFoundException $e) {
            }
        }

        $GLOBALS['TYPO3_REQUEST'] = $request;

        /** @var $TSFE \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, [], $pageId, 0, '', '', null, $MP);
        $TSFE->set_no_cache();
        $GLOBALS['TSFE'] = $TSFE;


        EidUtility::initLanguage();
        $TSFE->initFEuser();
        $TSFE->checkAlternativeIdMethods();

        $TSFE->id = $pageId;
        $TSFE->clear_preview();

        try {
            $TSFE->determineId();
        } catch (\TYPO3\CMS\Core\Http\ImmediateResponseException $e) {
            $result->addExceptions($e);
        }

        $TSFE->getConfigArray();

        Bootstrap::getInstance();

        // only needed for FrontendGroupRestriction.php
        $GLOBALS['TSFE']->gr_list =  $TSFE->gr_list;
        $TSFE->settingLanguage();
        $TSFE->settingLocale();

        $result->setTsfe($TSFE);

        return $result;
    }

    /**
     * @return TSFEBootstrapResult
     */
    public function bootstrap($pageId, $MP = '', $language = 0)
    {
        $result = new TSFEBootstrapResult();

        $context = GeneralUtility::makeInstance(Context::class);
        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $request = GeneralUtility::makeInstance(ServerRequest::class);

        $site = null;
        $siteLanguage = null;
        $pageArguments = null;
        if ($pageId !== null) {
            $pageArguments = GeneralUtility::makeInstance(PageArguments::class, $pageId, 0, ['MP' => $MP]);
            try {
                if ($MP !== '') {
                    [$origPageId, $pageIdToUse] = GeneralUtility::trimExplode('-', $MP);
                    $site = $siteFinder->getSiteByPageId($pageIdToUse);
                } else {
                    $site = $siteFinder->getSiteByPageId($pageId);
                }
                $siteLanguage = $site->getLanguageById($language);
                $request = $request->withAttribute('site', $site);
                $request = $request->withAttribute('language', $siteLanguage);
                $request = $request->withAttribute('routing', $pageArguments);
            } catch (SiteNotFoundException $e) {
                throw $e;
            }
        }

        $GLOBALS['TYPO3_REQUEST'] = $request;

        /** @var $TSFE \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $context, $site, $siteLanguage, $pageArguments);
        $TSFE->set_no_cache('', true);
        $GLOBALS['TSFE'] = $TSFE;

        $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);

        $TSFE->fe_user = $feUser;
        $TSFE->clear_preview();

        try {
            $TSFE->determineId();
        } catch (\TYPO3\CMS\Core\Http\ImmediateResponseException $e) {
            $result->addExceptions($e);
        }

        $template = GeneralUtility::makeInstance(TemplateService::class);
        $GLOBALS['TSFE']->tmpl = $template;
        $TSFE->getConfigArray();

        // only needed for FrontendGroupRestriction.php
        $GLOBALS['TSFE']->gr_list =  $TSFE->gr_list;
        $TSFE->settingLanguage();
        $TSFE->settingLocale();

        $result->setTsfe($TSFE);

        return $result;
    }
}
