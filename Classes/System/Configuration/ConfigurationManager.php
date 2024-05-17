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

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScriptFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Configuration manager old the configuration instance.
 * Singleton
 */
class ConfigurationManager implements SingletonInterface
{
    public function getTypoScriptFromRequest(ServerRequestInterface $request): TypoScriptConfiguration
    {
        $fullConfig = $this->getTypoScriptFrontendFromCore($request);
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
        return GeneralUtility::makeInstance(TypoScriptConfiguration::class, $fullConfig, $pageId);
    }

    /**
     * Retrieves the TypoScriptConfiguration object from configuration array, pageId, languageId and TypoScript
     * path that is used in the current context.
     */
    public function getTypoScriptConfiguration(int $contextPageId = null, int $contextLanguageId = 0): TypoScriptConfiguration
    {
        if ($contextPageId !== null) {
            $site = GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($contextPageId);
            $language = $site->getLanguageById($contextLanguageId);
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

    protected function getTypoScriptFrontendFromCore(ServerRequestInterface $request): array
    {
        $typo3Site = $request->getAttribute('site');
        $sysTemplateRows = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
            ->getConnectionForTable('sys_template')
            ->select(['*'], 'sys_template', ['pid' => $typo3Site->getRootPageId()])
            ->fetchAllAssociative();

        $frontendTypoScriptFactory = GeneralUtility::makeInstance(
            FrontendTypoScriptFactory::class,
            GeneralUtility::makeInstance(\Psr\Container\ContainerInterface::class),
            GeneralUtility::makeInstance(\Psr\EventDispatcher\EventDispatcherInterface::class),
            GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\IncludeTree\SysTemplateTreeBuilder::class),
            GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\Tokenizer\LossyTokenizer::class),
            GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\IncludeTree\Traverser\IncludeTreeTraverser::class),
            GeneralUtility::makeInstance(\TYPO3\CMS\Core\TypoScript\IncludeTree\Traverser\ConditionVerdictAwareIncludeTreeTraverser::class),
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

        $configurationArray = $frontendTypoScript->getSetupArray();
        return $configurationArray;
    }
}
