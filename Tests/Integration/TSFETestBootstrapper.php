<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Helper class to set up a TSFE object
 */
class TSFETestBootstrapper
{
    public function bootstrap(int $pageId): TypoScriptFrontendController
    {
        $context = GeneralUtility::makeInstance(Context::class);
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $pageArguments = new PageArguments($pageId, '0', []);
        $site = $siteFinder->getSiteByPageId($pageId);
        $siteLanguage = $site->getLanguageById(0);

        $request = new ServerRequest($site->getRouter()->generateUri($site->getRootPageId()));
        $request = $request->withAttribute('site', $site);
        $request = $request->withAttribute('language', $siteLanguage);
        $request = $request->withAttribute('routing', $pageArguments);

        $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);

        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $context, $site, $siteLanguage, $pageArguments, $feUser);
        $TSFE->set_no_cache('', true);
        $GLOBALS['TSFE'] = $TSFE;
        $TSFE->determineId($request);
        $GLOBALS['TYPO3_REQUEST'] = $TSFE->getFromCache($request);
        $TSFE->releaseLocks();
        $TSFE->newCObj($GLOBALS['TYPO3_REQUEST']);
        return $TSFE;
    }
}
