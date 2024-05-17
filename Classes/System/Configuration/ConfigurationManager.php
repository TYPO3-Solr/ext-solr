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
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Configuration manager old the configuration instance.
 * Singleton
 */
class ConfigurationManager implements SingletonInterface
{
    protected array $typoScriptConfigurations = [];

    /**
     * Resets the state of the configuration manager.
     */
    public function reset(): void
    {
        $this->typoScriptConfigurations = [];
    }

    public function getTypoScriptFromRequest(ServerRequestInterface $request): TypoScriptConfiguration
    {
        /** @var FrontendTypoScript $configurationArray */
        $frontendTypoScript = $request->getAttribute('frontend.typoscript');
        if ($frontendTypoScript) {
            return $this->getTypoScriptConfigurationInstance($configurationArray->getSetupArray());
        }
        $extbaseManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class);
        $extbaseManager->setRequest($request);
        $fullConfig = $extbaseManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT,
        );
        return $this->getTypoScriptConfigurationInstance($fullConfig);
    }

    public function createConfiguration(): TypoScriptConfiguration
    {
        return $this->getTypoScriptFromRequest($GLOBALS['TYPO3_REQUEST']);
    }

    /**
     * Retrieves the TypoScriptConfiguration object from configuration array, pageId, languageId and TypoScript
     * path that is used in the current context.
     */
    public function getTypoScriptConfiguration(
        array $configurationArray = null,
        int $contextPageId = null,
        int $contextLanguageId = 0,
        string $contextTypoScriptPath = '',
    ): TypoScriptConfiguration {
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
        if ($configurationArray == null) {
            if (isset($this->typoScriptConfigurations['default'])) {
                $configurationArray = $this->typoScriptConfigurations['default'];
            } elseif (!empty($GLOBALS['TSFE']->tmpl->setup) && is_array($GLOBALS['TSFE']->tmpl->setup)) {
                $configurationArray = $GLOBALS['TSFE']->tmpl->setup;
                $this->typoScriptConfigurations['default'] = $configurationArray;
            }
        }

        if (!is_array($configurationArray)) {
            $configurationArray = [];
        }

        if (!isset($configurationArray['plugin.']['tx_solr.'])) {
            $configurationArray['plugin.']['tx_solr.'] = [];
        }

        if ($contextPageId === null && !empty($GLOBALS['TSFE']->id)) {
            $contextPageId = $GLOBALS['TSFE']->id;
        }

        $hash = md5(serialize($configurationArray)) . '-' . $contextPageId . '-' . $contextLanguageId . '-' . $contextTypoScriptPath;
        if (isset($this->typoScriptConfigurations[$hash])) {
            return $this->typoScriptConfigurations[$hash];
        }

        $this->typoScriptConfigurations[$hash] = $this->getTypoScriptConfigurationInstance($configurationArray, $contextPageId);
        return $this->typoScriptConfigurations[$hash];
    }

    /**
     * This method is used to build the TypoScriptConfiguration.
     */
    protected function getTypoScriptConfigurationInstance(
        array $configurationArray = null,
        int $contextPageId = null,
    ): TypoScriptConfiguration {
        return GeneralUtility::makeInstance(
            TypoScriptConfiguration::class,
            $configurationArray,
            $contextPageId
        );
    }
}
