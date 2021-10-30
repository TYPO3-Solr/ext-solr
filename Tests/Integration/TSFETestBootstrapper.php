<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Localization\Locales;
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
     * @param int $pageId
     * @param string $MP
     * @param int $language
     * @return TSFEBootstrapResult
     * @throws SiteNotFoundException
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     */
    public function bootstrap(int $pageId, string $MP = '', int $language = 0): TSFEBootstrapResult
    {
        $result = new TSFEBootstrapResult();

        $context = GeneralUtility::makeInstance(Context::class);
        /* @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $request = GeneralUtility::makeInstance(ServerRequest::class);

        $pageArguments = GeneralUtility::makeInstance(PageArguments::class, $pageId, 0, ['MP' => $MP]);
        if ($MP !== '') {
            [$origPageId, $pageIdToUse] = GeneralUtility::trimExplode('-', $MP);
            $site = $siteFinder->getSiteByPageId($pageIdToUse);
        } else {
            $site = $siteFinder->getSiteByPageId($pageId);
        }
        $siteLanguage = $site->getLanguageById($language);
        Locales::setSystemLocaleFromSiteLanguage($siteLanguage);
        $request = $request->withAttribute('site', $site);
        $request = $request->withAttribute('language', $siteLanguage);
        $request = $request->withAttribute('routing', $pageArguments);
        $request = $request->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);

        $GLOBALS['TYPO3_REQUEST'] = $request;

        /* @var FrontendUserAuthentication $feUser */
        $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $feUser->initializeUserSessionManager();
        /* @var $TSFE TypoScriptFrontendController */
        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $context, $site, $siteLanguage, $pageArguments, $feUser);
        $TSFE->set_no_cache('', true);
        $GLOBALS['TSFE'] = $TSFE;
        $TSFE->clear_preview();

        $template = GeneralUtility::makeInstance(TemplateService::class);
        $GLOBALS['TSFE']->tmpl = $template;

        try {
            $GLOBALS['TSFE']->determineId($GLOBALS['TYPO3_REQUEST']);
            $GLOBALS['TSFE']->getConfigArray();
        } catch (ImmediateResponseException $e) {
            $result->addExceptions($e);
        }

        $result->setTsfe($TSFE);

        return $result;
    }
}
