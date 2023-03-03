<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use DateTimeImmutable;
use Exception;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Error\Http\AbstractServerErrorException;
use TYPO3\CMS\Core\Error\Http\InternalServerErrorException;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Aspect\PreviewAspect;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class TSFETestBootstrapper
 */
class TSFETestBootstrapper
{
    /**
     * @param int $pageId
     * @param string $MP
     * @param int $language
     * @return TSFEBootstrapResult
     *
     * @throws SiteNotFoundException
     * @throws InternalServerErrorException
     * @throws ServiceUnavailableException
     * @throws AbstractServerErrorException
     * @throws Exception
     */
    public function bootstrap(int $pageId, string $MP = '', int $language = 0): TSFEBootstrapResult
    {
        $result = new TSFEBootstrapResult();

        $context = GeneralUtility::makeInstance(Context::class);
        /* @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $request = GeneralUtility::makeInstance(ServerRequest::class);

        $pageArguments = GeneralUtility::makeInstance(PageArguments::class, $pageId, '0', ['MP' => $MP]);
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

        // reset PageRenderer instance, to avoid assigning old contents in current flow.
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->setBodyContent('');
        /* @var TypoScriptFrontendController $TSFE */
        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $context, $site, $siteLanguage, $pageArguments, $feUser);
        $TSFE->set_no_cache('', true);
        $GLOBALS['TSFE'] = $TSFE;

        $GLOBALS['SIM_EXEC_TIME'] = $GLOBALS['EXEC_TIME'];
        $GLOBALS['SIM_ACCESS_TIME'] = $GLOBALS['ACCESS_TIME'];
        $context->setAspect(
            'frontend.preview',
            GeneralUtility::makeInstance(PreviewAspect::class)
        );
        $context->setAspect(
            'date',
            GeneralUtility::makeInstance(
                DateTimeAspect::class,
                new DateTimeImmutable('@' . $GLOBALS['SIM_EXEC_TIME'])
            )
        );
        $context->setAspect(
            'visibility',
            GeneralUtility::makeInstance(VisibilityAspect::class)
        );

        $template = GeneralUtility::makeInstance(TemplateService::class);
        $GLOBALS['TSFE']->tmpl = $template;

        try {
            $GLOBALS['TSFE']->determineId($GLOBALS['TYPO3_REQUEST']);
            $GLOBALS['TYPO3_REQUEST'] = $GLOBALS['TSFE']->getFromCache($request);
            $GLOBALS['TSFE']->releaseLocks();
        } catch (ImmediateResponseException $e) {
            $result->addExceptions($e);
        }

        $result->setTsfe($TSFE);

        return $result;
    }
}
