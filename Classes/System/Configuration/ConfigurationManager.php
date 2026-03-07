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
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
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
 * Configuration manager that holds the configuration instance.
 */
readonly class ConfigurationManager implements SingletonInterface
{
    protected SiteFinder $siteFinder;

    public function __construct(
        ?SiteFinder $siteFinder = null,
    ) {
        $this->siteFinder = $siteFinder ?? GeneralUtility::makeInstance(SiteFinder::class);
    }

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
                // Fallback to root-page
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
     * @throws NoSuchCacheException
     * @throws SiteNotFoundException
     * @throws InvalidRouteArgumentsException
     */
    public function getTypoScriptConfiguration(?int $contextPageId = null, int $contextLanguageId = 0): TypoScriptConfiguration
    {
        if ($contextPageId !== null) {
            $site = $this->siteFinder->getSiteByPageId($contextPageId);
            $language = $site->getLanguageById($contextLanguageId);
            // @todo: Storage-Folder can not be used to get TypoScript Config!!!
            $uri = $site->getRouter()->generateUri($contextPageId, ['_language' => $language]);
            $request = (new ServerRequest($uri, 'GET'))
                ->withAttribute('site', $site)
                ->withAttribute('frontend.page.information', $this->getPageInformation($contextPageId))
                ->withQueryParams(['id' => $contextPageId])
                ->withAttribute('language', $language);
            return $this->getTypoScriptFromRequest($request);
        }

        if (isset($GLOBALS['TYPO3_REQUEST'])) {
            return $this->getTypoScriptFromRequest($GLOBALS['TYPO3_REQUEST']);
        }

        // fallback: find the first site, use the first language, that's it
        $allSites = $this->siteFinder->getAllSites(false);

        // No site found, let's return an empty configuration object
        if ($allSites === []) {
            return new TypoScriptConfiguration([]);
        }

        $site = reset($allSites);
        $language = $site->getDefaultLanguage();
        $uri = $site->getRouter()->generateUri($site->getRootPageId(), ['_language' => $language]);
        $request = (new ServerRequest($uri, 'GET'))
            ->withAttribute('site', $site)
            ->withAttribute('frontend.page.information', $this->getPageInformation($site->getRootPageId()))
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
        $frontendTypoScriptFactory = $this->getFrontendTypoScriptFactory();

        $expressionMatcherVariables = ['request' => $request];
        $pageInformation = $request->getAttribute('frontend.page.information');
        if ($pageInformation instanceof PageInformation) {
            $expressionMatcherVariables['pageId'] = $pageInformation->getId();
            $expressionMatcherVariables['page'] = $pageInformation->getPageRecord();
        } else {
            $pageUid = (int)(
                $request->getParsedBody()['id']
                ?? $request->getQueryParams()['id']
                ?? $typo3Site?->getRootPageId()
            );
            if ($pageUid !== 0) {
                $expressionMatcherVariables['pageId'] = $pageUid;
                $expressionMatcherVariables['page'] = BackendUtility::getRecord('pages', $pageUid);
            }
        }

        if ($typo3Site instanceof Site) {
            $expressionMatcherVariables['site'] = $typo3Site;
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
        $coreContext = $this->getCoreContextWithIncludedHiddenRecords();
        $rootLine = $this->getRootLineUtility($request, $coreContext)->get();
        if ($rootLine === []) {
            return [];
        }

        return $this->getSysTemplateRepository($coreContext)->getSysTemplateRowsByRootline(
            $rootLine,
            $request,
        );
    }

    protected function getSysTemplateRepository(Context $coreContext): SysTemplateRepository
    {
        return GeneralUtility::makeInstance(
            SysTemplateRepository::class,
            GeneralUtility::makeInstance(EventDispatcherInterface::class),
            GeneralUtility::makeInstance(ConnectionPool::class),
            $coreContext,
        );
    }

    protected function getPageUid(ServerRequestInterface $request): int
    {
        return (int)(
            $request->getParsedBody()['id']
            ?? $request->getQueryParams()['id']
            ?? $request->getAttribute('frontend.controller')?->id
            ?? $request->getAttribute('site')?->getRootPageId()
        );
    }

    protected function getFrontendTypoScriptFactory(): FrontendTypoScriptFactory
    {
        return GeneralUtility::makeInstance(
            FrontendTypoScriptFactory::class,
            GeneralUtility::makeInstance(ContainerInterface::class),
            GeneralUtility::makeInstance(EventDispatcherInterface::class),
            GeneralUtility::makeInstance(SysTemplateTreeBuilder::class),
            GeneralUtility::makeInstance(LossyTokenizer::class),
            GeneralUtility::makeInstance(IncludeTreeTraverser::class),
            GeneralUtility::makeInstance(ConditionVerdictAwareIncludeTreeTraverser::class),
        );
    }

    protected function getPageInformation(int $rootPageUid): PageInformation
    {
        $pageInformation = new PageInformation();
        $pageInformation->setId($rootPageUid);
        $pageInformation->setPageRecord(BackendUtility::getRecord('pages', $rootPageUid));
        $pageInformation->setContentFromPid($rootPageUid);

        return $pageInformation;
    }

    protected function getRootLineUtility(ServerRequestInterface $request, Context $coreContext): RootLineUtility
    {
        return GeneralUtility::makeInstance(
            RootLineUtility::class,
            $this->getPageUid($request),
            '', // @todo: tag: MountPoint,
            $coreContext,
        );
    }

    protected function getCoreContextWithIncludedHiddenRecords(): Context
    {
        $coreContext = clone $this->getCoreContext();
        $coreContext->setAspect(
            'visibility',
            new VisibilityAspect(false, false),
        );

        return $coreContext;
    }

    protected function getCoreContext(): Context
    {
        return GeneralUtility::makeInstance(Context::class);
    }
}
