<?php

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

namespace ApacheSolrForTypo3\Solr\System\Configuration;

use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScriptFactory;
use TYPO3\CMS\Core\TypoScript\IncludeTree\SysTemplateRepository;
use TYPO3\CMS\Core\TypoScript\IncludeTree\SysTemplateTreeBuilder;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Traverser\ConditionVerdictAwareIncludeTreeTraverser;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Traverser\IncludeTreeTraverser;
use TYPO3\CMS\Core\TypoScript\Tokenizer\LossyTokenizer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Configuration manager old the configuration instance.
 * Singleton
 */
class ConfigurationManager implements SingletonInterface
{
    /**
     * @throws DBALException
     * @throws JsonException
     * @throws NoSuchCacheException
     */
    public function getTypoScriptFromRequest(ServerRequestInterface $request): TypoScriptConfiguration
    {
        $pageId = $request->getParsedBody()['id'] ?? $request->getQueryParams()['id'] ?? null;
        if ($pageId !== null) {
            $pageId = (int)$pageId;
        } else {
            $routingAttribute = $request->getAttribute('routing');
            if ($routingAttribute instanceof PageArguments) {
                $pageId = $routingAttribute->getPageId();
            } else {
                // Fallback to root page
                $site = $request->getAttribute('site');
                if ($site instanceof Site) {
                    $pageId = $site->getRootPageId();
                }
            }
        }
        try {
            $fullConfig = $request->getAttribute('frontend.typoscript')?->getSetupArray();
        } catch (RuntimeException) {
            $fullConfig = null;
        }
        if ($fullConfig === null) {
            $cache = GeneralUtility::makeInstance(CacheManager::class)->getCache('hash');
            $cacheIdentifier = $pageId . '_' . ($request->getAttribute('language')?->getLanguageId() ?? 0);
            if (!$cache->has($cacheIdentifier)) {
                // Fallback to full TypoScript configuration
                $fullConfig = $this->getCoreTypoScriptFrontendByRequest($request)->getSetupArray();
                $cache->set($cacheIdentifier, $fullConfig);
            } else {
                $fullConfig = $cache->get($cacheIdentifier);
            }
        }
        return GeneralUtility::makeInstance(TypoScriptConfiguration::class, $fullConfig, $pageId);
    }

    /**
     * Retrieves the TypoScriptConfiguration object from configuration array, pageId, languageId and TypoScript
     * path that is used in the current context.
     *
     * @throws DBALException
     * @throws JsonException
     * @throws SiteNotFoundException
     * @throws NoSuchCacheException
     */
    public function getTypoScriptConfiguration(?int $contextPageId = null, int $contextLanguageId = 0): TypoScriptConfiguration
    {
        if ($contextPageId !== null && BackendUtility::getRecord('pages', $contextPageId) !== null) {
            $site = GeneralUtility::makeInstance(SiteFinder::class)
                ->getSiteByPageId($contextPageId);
            $language = $site->getLanguageById($contextLanguageId);
            // @todo: Storage-Folder can not be used to get TypoScript Config!!!
            $uri = $site->getRouter()->generateUri($contextPageId, ['_language' => $language]);
            $pageInformation = new PageInformation();
            $pageInformation->setId($contextPageId);
            $pageInformation->setPageRecord(BackendUtility::getRecord('pages', $contextPageId));
            $pageInformation->setContentFromPid($contextPageId);
            $request = (new ServerRequest($uri, 'GET'))
                ->withAttribute('site', $site)
                ->withAttribute('frontend.page.information', $pageInformation)
                ->withQueryParams(['id' => $contextPageId])
                ->withAttribute('language', $language);
            return $this->getTypoScriptFromRequest($request);
        }
        if (isset($GLOBALS['TYPO3_REQUEST'])) {
            return $this->getTypoScriptFromRequest($GLOBALS['TYPO3_REQUEST']);
        }
        // fallback: find the first site, use the first language, that's it
        $allSites = GeneralUtility::makeInstance(SiteFinder::class)->getAllSites(false);
        // No site found, lets return an empty configuration object
        if ($allSites === []) {
            return new TypoScriptConfiguration([]);
        }
        $site = reset($allSites);
        $language = $site->getDefaultLanguage();
        $uri = $site->getRouter()->generateUri($site->getRootPageId(), ['_language' => $language]);
        $pageInformation = new PageInformation();
        $pageInformation->setId($site->getRootPageId());
        $pageInformation->setPageRecord(BackendUtility::getRecord('pages', $site->getRootPageId()));
        $pageInformation->setContentFromPid($site->getRootPageId());
        $request = (new ServerRequest($uri, 'GET'))
            ->withAttribute('site', $site)
            ->withAttribute('frontend.page.information', $pageInformation)
            ->withQueryParams(['id' => $site->getRootPageId()])
            ->withAttribute('language', $language);
        return $this->getTypoScriptFromRequest($request);
    }

    /**
     * @throws DBALException
     * @throws JsonException
     */
    public function getCoreTypoScriptFrontendByRequest(ServerRequestInterface $request): FrontendTypoScript
    {
        if ($request->getAttribute('frontend.typoscript') instanceof FrontendTypoScript) {
            return $request->getAttribute('frontend.typoscript');
        }

        $typo3Site = $request->getAttribute('site');
        $sysTemplateRows = $this->getSysTemplateRowsForAssociatedContextPageId($request);

        $frontendTypoScriptFactory = GeneralUtility::makeInstance(
            FrontendTypoScriptFactory::class,
            GeneralUtility::makeInstance(ContainerInterface::class),
            GeneralUtility::makeInstance(EventDispatcherInterface::class),
            GeneralUtility::makeInstance(SysTemplateTreeBuilder::class),
            GeneralUtility::makeInstance(LossyTokenizer::class),
            GeneralUtility::makeInstance(IncludeTreeTraverser::class),
            GeneralUtility::makeInstance(ConditionVerdictAwareIncludeTreeTraverser::class),
        );

        $expressionMatcherVariables = ['request' => $request];
        $pageInformation = $request->getAttribute('frontend.page.information');
        if ($pageInformation instanceof PageInformation) {
            $expressionMatcherVariables['pageId'] = $pageInformation->getId();
            $expressionMatcherVariables['page'] = $pageInformation->getPageRecord();
        } else {
            $pageUid = (int)(
                $request->getParsedBody()['id']
                ?? $request->getQueryParams()['id']
                ?? $request->getAttribute('site')?->getRootPageId()
            );
            if ($pageUid !== 0) {
                $expressionMatcherVariables['pageId'] = $pageUid;
                $expressionMatcherVariables['page'] = BackendUtility::getRecord('pages', $pageUid);
            }
        }
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            $expressionMatcherVariables['site'] = $site;
        }
        $frontendTypoScript = $frontendTypoScriptFactory->createSettingsAndSetupConditions(
            $typo3Site,
            $sysTemplateRows,
            $expressionMatcherVariables,
            null,
        );
        return $frontendTypoScriptFactory->createSetupConfigOrFullSetup(
            true,
            $frontendTypoScript,
            $typo3Site,
            $sysTemplateRows,
            $expressionMatcherVariables,
            '0',
            null,
            null,
        );
    }

    /**
     * @return array|array{
     *    'uid': int,
     *    'pid': int,
     *    'tstamp': int,
     *    'crdate': int,
     *    'deleted': int,
     *    'hidden': int,
     *    'starttime': int,
     *    'endtime': int,
     *    'sorting': int,
     *    'description': string,
     *    'tx_impexp_origuid': int,
     *    'title': string,
     *    'root': int,
     *    'clear': int,
     *    'constants': string,
     *    'include_static_file': string,
     *    'basedOn': string,
     *    'includeStaticAfterBasedOn': int,
     *    'config': string,
     *    'static_file_mode': int,
     * }
     *
     * @throws DBALException
     */
    protected function getSysTemplateRowsForAssociatedContextPageId(ServerRequestInterface $request): array
    {
        $pageUid = (int)(
            $request->getParsedBody()['id']
            ?? $request->getQueryParams()['id']
            ?? $request->getAttribute('frontend.controller')?->id
            ?? $request->getAttribute('site')?->getRootPageId()
        );

        /** @var Context $coreContext */
        $coreContext = clone GeneralUtility::makeInstance(Context::class);
        $coreContext->setAspect(
            'visibility',
            GeneralUtility::makeInstance(
                VisibilityAspect::class,
                false,
                false,
            ),
        );
        /** @var RootLineUtility $rootlineUtility */
        $rootlineUtility = GeneralUtility::makeInstance(
            RootLineUtility::class,
            $pageUid,
            '', // @todo: tag: MountPoint,
            $coreContext,
        );
        $rootline = $rootlineUtility->get();
        if ($rootline === []) {
            return [];
        }

        /** @var SysTemplateRepository $sysTemplateRepository */
        $sysTemplateRepository = GeneralUtility::makeInstance(
            SysTemplateRepository::class,
            GeneralUtility::makeInstance(EventDispatcherInterface::class),
            GeneralUtility::makeInstance(ConnectionPool::class),
            $coreContext,
        );

        return $sysTemplateRepository->getSysTemplateRowsByRootline(
            $rootline,
            $request,
        );
    }
}
