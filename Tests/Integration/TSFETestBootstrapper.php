<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * Class TSFETestBootstrapper
 * @package ApacheSolrForTypo3\Solr\Tests\Integration
 */
class TSFETestBootstrapper
{
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
