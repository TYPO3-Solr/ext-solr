<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Localization\Locales;
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
     * TSFEBootstrapResult
     *
     * @param $pageId
     * @param string $MP
     * @param int $language
     * @return TSFEBootstrapResult
     * @throws ImmediateResponseException
     * @throws SiteNotFoundException
     * @throws ServiceUnavailableException
     */
    public function bootstrap($pageId, $MP = '', $language = 0)
    {
        $result = new TSFEBootstrapResult();
        /* @var Context $context */
        $context = GeneralUtility::makeInstance(Context::class);
        /* @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        /* @var ServerRequest $request */
        $request = GeneralUtility::makeInstance(ServerRequest::class);

        $site = null;
        $siteLanguage = null;
        $pageArguments = null;
        if ($pageId !== null) {
            /* @var PageArguments $pageArguments */
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

        /* @var TypoScriptFrontendController $TSFE */
        $TSFE = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $context, $site, $siteLanguage, $pageArguments);
        $TSFE->set_no_cache('', true);
        $GLOBALS['TSFE'] = $TSFE;
        /* @var FrontendUserAuthentication $feUser */
        $feUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);

        $TSFE->fe_user = $feUser;
        $TSFE->clear_preview();

        try {
            $TSFE->determineId();
        } catch (ImmediateResponseException $e) {
            $result->addExceptions($e);
        }
        /* @var TemplateService $feUser */
        $template = GeneralUtility::makeInstance(TemplateService::class);
        $GLOBALS['TSFE']->tmpl = $template;
        $TSFE->getConfigArray();

        // only needed for FrontendGroupRestriction.php
        $GLOBALS['TSFE']->gr_list =  $TSFE->gr_list;
        $TSFE->settingLanguage();
        Locales::setSystemLocaleFromSiteLanguage($siteLanguage);

        $result->setTsfe($TSFE);

        return $result;
    }
}
