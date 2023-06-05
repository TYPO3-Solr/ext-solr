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

namespace ApacheSolrForTypo3\Solr\Controller;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestBuilder;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager as SolrConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Service\ConfigurationService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class AbstractBaseController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractBaseController extends ActionController
{
    /**
     * The HTTP message for 503 error from Apache Solr server.
     */
    protected const STATUS_503_MESSAGE = 'Apache Solr Server is not available.';

    private ?ContentObjectRenderer $contentObjectRenderer = null;

    protected ?TypoScriptFrontendController $typoScriptFrontendController = null;

    private ?SolrConfigurationManager $solrConfigurationManager = null;

    /**
     * The configuration is private if you need it please get it from the SolrVariableProvider of RenderingContext.
     */
    protected ?TypoScriptConfiguration $typoScriptConfiguration = null;

    protected ?SearchResultSetService $searchService = null;

    protected ?SearchRequestBuilder $searchRequestBuilder = null;

    protected bool $resetConfigurationBeforeInitialize = true;

    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
        // @extensionScannerIgnoreLine
        $this->contentObjectRenderer = $this->configurationManager->getContentObject();
        $this->arguments = GeneralUtility::makeInstance(Arguments::class);
    }

    public function setContentObjectRenderer(ContentObjectRenderer $contentObjectRenderer): void
    {
        $this->contentObjectRenderer = $contentObjectRenderer;
    }

    public function getContentObjectRenderer(): ?ContentObjectRenderer
    {
        return $this->contentObjectRenderer;
    }

    public function injectSolrConfigurationManager(SolrConfigurationManager $configurationManager): void
    {
        $this->solrConfigurationManager = $configurationManager;
    }

    public function setResetConfigurationBeforeInitialize(bool $resetConfigurationBeforeInitialize): void
    {
        $this->resetConfigurationBeforeInitialize = $resetConfigurationBeforeInitialize;
    }

    /**
     * Initialize action
     */
    protected function initializeAction(): void
    {
        // Reset configuration (to reset flexform overrides) if resetting is enabled
        if ($this->resetConfigurationBeforeInitialize) {
            $this->solrConfigurationManager->reset();
        }
        /** @var TypoScriptService $typoScriptService */
        $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);

        // Merge settings done by typoscript with solrConfiguration plugin.tx_solr (obsolete when part of ext:solr)
        $frameWorkConfiguration = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
        $pluginSettings = [];
        foreach (['search', 'settings', 'suggest', 'statistics', 'logging', 'general', 'solr', 'view'] as $key) {
            if (isset($frameWorkConfiguration[$key])) {
                $pluginSettings[$key] = $frameWorkConfiguration[$key];
            }
        }

        $this->typoScriptConfiguration = $this->solrConfigurationManager->getTypoScriptConfiguration();
        if ($pluginSettings !== []) {
            $this->typoScriptConfiguration->mergeSolrConfiguration(
                $typoScriptService->convertPlainArrayToTypoScriptArray($pluginSettings),
                true,
                false
            );
        }

        if (!empty($this->contentObjectRenderer->data['pi_flexform'])) {
            GeneralUtility::makeInstance(ConfigurationService::class)
                ->overrideConfigurationWithFlexFormSettings(
                    $this->contentObjectRenderer->data['pi_flexform'],
                    $this->typoScriptConfiguration
                );
        }

        parent::initializeAction();
        $this->typoScriptFrontendController = $GLOBALS['TSFE'];
        $this->initializeSettings();

        if ($this->actionMethodName !== 'solrNotAvailableAction') {
            $this->initializeSearch();
        }
    }

    /**
     * Inject settings of plugin.tx_solr
     */
    protected function initializeSettings(): void
    {
        $typoScriptService = GeneralUtility::makeInstance(TypoScriptService::class);

        // Make sure plugin.tx_solr.settings are available in the view as {settings}
        $this->settings = $typoScriptService->convertTypoScriptArrayToPlainArray(
            $this->typoScriptConfiguration->getObjectByPathOrDefault('plugin.tx_solr.settings.')
        );
    }

    /**
     * Initialize the Solr connection and
     * test the connection through a ping
     */
    protected function initializeSearch(): void
    {
        try {
            $solrConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByTypo3Site(
                $this->typoScriptFrontendController->getSite(),
                $this->typoScriptFrontendController->getLanguage()->getLanguageId()
            );

            $search = GeneralUtility::makeInstance(Search::class, $solrConnection);

            $this->searchService = GeneralUtility::makeInstance(
                SearchResultSetService::class,
                $this->typoScriptConfiguration,
                $search
            );
        } catch (NoSolrConnectionFoundException) {
            $this->logSolrUnavailable();
        }
    }

    protected function getSearchRequestBuilder(): SearchRequestBuilder
    {
        if ($this->searchRequestBuilder === null) {
            $this->searchRequestBuilder = GeneralUtility::makeInstance(SearchRequestBuilder::class, $this->typoScriptConfiguration);
        }

        return $this->searchRequestBuilder;
    }

    /**
     * Called when the solr server is unavailable.
     */
    protected function logSolrUnavailable(): void
    {
        if ($this->typoScriptConfiguration->getLoggingExceptions()) {
            $logger = GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__);
            $logger->error('Solr server is not available');
        }
    }
}
