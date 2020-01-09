<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use ApacheSolrForTypo3\Solr\Util;
use Composer\Autoload\ClassLoader;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Context\Context;
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
    public function legacyBootstrap($TYPO3_CONF_VARS = [], $siteOrId = 1, $type = 0, $no_cache = '', $cHash = '', $_2 = null, $MP = '', $RDCT = '', $config = [])
    {
        $result = new TSFEBootstrapResult();

        /** @var $TSFE \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $TYPO3_CONF_VARS, $siteOrId, $type, $no_cache, $cHash, $_2, $MP, $RDCT);
        $TSFE->set_no_cache();
        $GLOBALS['TSFE'] = $TSFE;


        EidUtility::initLanguage();
        $TSFE->initFEuser();
        $TSFE->checkAlternativeIdMethods();

        $TSFE->id = $siteOrId;
        $TSFE->clear_preview();

        try {
            $TSFE->determineId();
        } catch (\TYPO3\CMS\Core\Http\ImmediateResponseException $e) {
            $result->addExceptions($e);
        }

        $TSFE->initTemplate();
        $TSFE->getConfigArray();
        $TSFE->config = array_merge($TSFE->config, $config);

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
    public function bootstrap($TYPO3_CONF_VARS = [], $pageId, $no_cache = '', $cHash = '', $_2 = null, $MP = '', $RDCT = '', $config = [])
    {
        $result = new TSFEBootstrapResult();

        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $site = $siteFinder->getSiteByPageId($pageId);
        $siteLanguage = $site->getDefaultLanguage();

        /** @var $TSFE \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $TYPO3_CONF_VARS, $site, $siteLanguage, $no_cache, $cHash, $_2, $MP, $RDCT);
        $TSFE->set_no_cache();
        $GLOBALS['TSFE'] = $TSFE;

        $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);

        $TSFE->id = $pageId;
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
        $TSFE->config = array_merge($TSFE->config, $config);

        // only needed for FrontendGroupRestriction.php
        $GLOBALS['TSFE']->gr_list =  $TSFE->gr_list;
        $TSFE->settingLanguage();
        $TSFE->settingLocale();

        $result->setTsfe($TSFE);

        return $result;
    }
}