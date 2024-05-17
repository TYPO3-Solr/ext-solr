<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Helper class to set up a TSFE object
 */
class TSFETestBootstrapper
{
    public function bootstrap(int $pageId): TypoScriptFrontendController
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $pageArguments = new PageArguments($pageId, '0', []);
        $site = $siteFinder->getSiteByPageId($pageId);
        $siteLanguage = $site->getLanguageById(0);
        $pageInformation = new PageInformation();
        $pageInformation->setId($pageId);
        $pageInformation->setPageRecord(['uid' => $pageId]);

        $request = new ServerRequest($site->getRouter()->generateUri($site->getRootPageId()));
        $request = $request->withAttribute('site', $site);
        $request = $request->withAttribute('language', $siteLanguage);
        $request = $request->withAttribute('routing', $pageArguments);
        $request = $request->withAttribute('frontend.user', GeneralUtility::makeInstance(FrontendUserAuthentication::class));
        $request = $request->withAttribute('frontend.page.information', $pageInformation);
        $GLOBALS['TYPO3_REQUEST'] = $request;

        /** @var TypoScriptFrontendController $TSFE */
        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class);
        $TSFE->set_no_cache();
        $TSFE->id = $pageId;
        $GLOBALS['TSFE'] = $TSFE;
        $TSFE->newCObj($request);
        return $TSFE;
    }
}
