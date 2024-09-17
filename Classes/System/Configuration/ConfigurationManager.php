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
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\VisibilityAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScriptFactory;
use TYPO3\CMS\Core\TypoScript\IncludeTree\SysTemplateRepository;
use TYPO3\CMS\Core\TypoScript\IncludeTree\SysTemplateTreeBuilder;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Traverser\ConditionVerdictAwareIncludeTreeTraverser;
use TYPO3\CMS\Core\TypoScript\IncludeTree\Traverser\IncludeTreeTraverser;
use TYPO3\CMS\Core\TypoScript\Tokenizer\LossyTokenizer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Configuration manager old the configuration instance.
 * Singleton
 */
class ConfigurationManager implements SingletonInterface
{
    /**
     * @throws DBALException
     * @throws JsonException
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
        $fullConfig = $this->getTypoScriptFrontendFromCore($request);
        return GeneralUtility::makeInstance(TypoScriptConfiguration::class, $fullConfig, $pageId);
    }

    /**
     * Retrieves the TypoScriptConfiguration object from configuration array, pageId, languageId and TypoScript
     * path that is used in the current context.
     *
     * @throws DBALException
     * @throws JsonException
     * @throws SiteNotFoundException
     */
    public function getTypoScriptConfiguration(int $contextPageId = null, int $contextLanguageId = 0): TypoScriptConfiguration
    {
        if ($contextPageId !== null) {
            $site = GeneralUtility::makeInstance(SiteFinder::class)
                ->getSiteByPageId($contextPageId);
            $language = $site->getLanguageById($contextLanguageId);
            // @todo: Storage-Folder can not be used to get TypoScript Config!!!
            $uri = $site->getRouter()->generateUri($contextPageId, ['_language' => $language]);
            $request = (new ServerRequest($uri, 'GET'))
                ->withAttribute('site', $site)
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
        $request = (new ServerRequest($uri, 'GET'))
            ->withAttribute('site', $site)
            ->withQueryParams(['id' => $site->getRootPageId()])
            ->withAttribute('language', $language);
        return $this->getTypoScriptFromRequest($request);
    }

    /**
     * @throws DBALException
     * @throws JsonException
     */
    protected function getTypoScriptFrontendFromCore(ServerRequestInterface $request): array
    {
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
        $frontendTypoScript = $frontendTypoScriptFactory->createSettingsAndSetupConditions(
            $typo3Site,
            $sysTemplateRows,
            [],
            null,
        );
        $frontendTypoScript = $frontendTypoScriptFactory->createSetupConfigOrFullSetup(
            true,
            $frontendTypoScript,
            $typo3Site,
            $sysTemplateRows,
            [],
            '0',
            null,
            null,
        );

        return $frontendTypoScript->getSetupArray();
    }

    /**
     * @throws DBALException
     */
    protected function getSysTemplateRowsForAssociatedContextPageId(ServerRequestInterface $request): array
    {
        $pageUid = (int)(
            $request->getParsedBody()['id']
            ?? $request->getQueryParams()['id']
            ?? $request->getAttribute('site')?->getRootPageId()
        );

        /** @var Context $coreContext */
        $coreContext = clone GeneralUtility::makeInstance(Context::class);
        $coreContext->setAspect(
            'visibility',
            GeneralUtility::makeInstance(
                VisibilityAspect::class,
                false,
                false
            )
        );
        /** @var RootLineUtility $rootlineUtility */
        $rootlineUtility = GeneralUtility::makeInstance(
            RootLineUtility::class,
            $pageUid,
            '', // @todo: tag: MountPoint,
            $coreContext,
        );

        /** @var SysTemplateRepository $sysTemplateRepository */
        $sysTemplateRepository = GeneralUtility::makeInstance(
            SysTemplateRepository::class,
            GeneralUtility::makeInstance(EventDispatcherInterface::class),
            GeneralUtility::makeInstance(ConnectionPool::class),
            $coreContext,
        );

        return $sysTemplateRepository->getSysTemplateRowsByRootline(
            $rootlineUtility->get(),
            $request
        );
    }
}
