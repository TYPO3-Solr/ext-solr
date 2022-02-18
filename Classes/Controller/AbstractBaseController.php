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
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Service\ConfigurationService;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager as SolrConfigurationManager;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Class AbstractBaseController
 *
 * @property SolrControllerContext $controllerContext
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractBaseController extends ActionController
{
    /**
     * The HTTP code 503 message.
     * @var string
     */
    protected const STATUS_503_MESSAGE = 'Apache Solr Server is not available.';

    /**
     * @var ContentObjectRenderer|null
     */
    private ?ContentObjectRenderer $contentObjectRenderer = null;

    /**
     * @var TypoScriptFrontendController|null
     */
    protected ?TypoScriptFrontendController $typoScriptFrontendController = null;

    /**
     * @var SolrConfigurationManager|null
     */
    private ?SolrConfigurationManager $solrConfigurationManager = null;

    /**
     * The configuration is private if you need it please get it from the controllerContext.
     *
     * @var TypoScriptConfiguration|null
     */
    protected ?TypoScriptConfiguration $typoScriptConfiguration = null;

    /**
     * @var SearchResultSetService|null
     */
    protected ?SearchResultSetService $searchService = null;

    /**
     * @var SearchRequestBuilder|null
     */
    protected ?SearchRequestBuilder $searchRequestBuilder = null;

    /**
     * @var bool
     */
    protected bool $resetConfigurationBeforeInitialize = true;

    /**
     * @param ConfigurationManagerInterface $configurationManager
     * @return void
     */
    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager)
    {
        $this->configurationManager = $configurationManager;
        // @extensionScannerIgnoreLine
        $this->contentObjectRenderer = $this->configurationManager->getContentObject();
    }

    /**
     * @param ContentObjectRenderer $contentObjectRenderer
     */
    public function setContentObjectRenderer(ContentObjectRenderer $contentObjectRenderer)
    {
        $this->contentObjectRenderer = $contentObjectRenderer;
    }

    /**
     * @return ContentObjectRenderer|null
     */
    public function getContentObjectRenderer(): ?ContentObjectRenderer
    {
        return $this->contentObjectRenderer;
    }

    /**
     * @param SolrConfigurationManager $configurationManager
     */
    public function injectSolrConfigurationManager(SolrConfigurationManager $configurationManager)
    {
        $this->solrConfigurationManager = $configurationManager;
    }

    /**
     * @param bool $resetConfigurationBeforeInitialize
     */
    public function setResetConfigurationBeforeInitialize(bool $resetConfigurationBeforeInitialize)
    {
        $this->resetConfigurationBeforeInitialize = $resetConfigurationBeforeInitialize;
    }

    /**
     * Initialize the controller context
     *
     * @return ControllerContext ControllerContext to be passed to the view
     * @api
     */
    protected function buildControllerContext()
    {
        /** @var $controllerContext SolrControllerContext */
        $controllerContext = $this->objectManager->get(SolrControllerContext::class);
        $controllerContext->setRequest($this->request);
//        $controllerContext->setResponse($this->response);
        if ($this->arguments !== null) {
            $controllerContext->setArguments($this->arguments);
        }
        $controllerContext->setUriBuilder($this->uriBuilder);

        $controllerContext->setTypoScriptConfiguration($this->typoScriptConfiguration);

        return $controllerContext;
    }

    /**
     * Initialize action
     * @throws AspectNotFoundException
     */
    protected function initializeAction()
    {
        // Reset configuration (to reset flexform overrides) if resetting is enabled
        if ($this->resetConfigurationBeforeInitialize) {
            $this->solrConfigurationManager->reset();
        }
        /** @var TypoScriptService $typoScriptService */
        $typoScriptService = $this->objectManager->get(TypoScriptService::class);

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
            $this->objectManager->get(ConfigurationService::class)
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
     *
     * @return void
     */
    protected function initializeSettings()
    {
        /** @var $typoScriptService TypoScriptService */
        $typoScriptService = $this->objectManager->get(TypoScriptService::class);

        // Make sure plugin.tx_solr.settings are available in the view as {settings}
        $this->settings = $typoScriptService->convertTypoScriptArrayToPlainArray(
            $this->typoScriptConfiguration->getObjectByPathOrDefault('plugin.tx_solr.settings.', [])
        );
    }

    /**
     * Initialize the Solr connection and
     * test the connection through a ping
     * @throws AspectNotFoundException
     */
    protected function initializeSearch()
    {
        /** @var ConnectionManager $solrConnection */
        try {
            $solrConnection = $this->objectManager->get(ConnectionManager::class)->getConnectionByPageId($this->typoScriptFrontendController->id, Util::getLanguageUid(), $this->typoScriptFrontendController->MP);
            $search = $this->objectManager->get(Search::class, $solrConnection);

            /** @noinspection PhpParamsInspection */
            $this->searchService = $this->objectManager->get(
                SearchResultSetService::class,
                /** @scrutinizer ignore-type */
                $this->typoScriptConfiguration,
                /** @scrutinizer ignore-type */
                $search
            );
        } catch (NoSolrConnectionFoundException $e) {
            $this->logSolrUnavailable();
        }
    }

    /**
     * @return SearchRequestBuilder
     */
    protected function getSearchRequestBuilder(): SearchRequestBuilder
    {
        if ($this->searchRequestBuilder === null) {
            $this->searchRequestBuilder = GeneralUtility::makeInstance(SearchRequestBuilder::class, /** @scrutinizer ignore-type */ $this->typoScriptConfiguration);
        }

        return $this->searchRequestBuilder;
    }

    /**
     * Called when the solr server is unavailable.
     *
     * @return void
     */
    protected function logSolrUnavailable()
    {
        if ($this->typoScriptConfiguration->getLoggingExceptions()) {
            /** @var SolrLogManager $logger */
            $logger = GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);
            $logger->log(SolrLogManager::ERROR, 'Solr server is not available');
        }
    }

    /**
     * Emits signal for various actions
     *
     * @param string $className Name of the class containing the signal
     * @param string $signalName Name of the signal slot
     * @param array $signalArguments arguments for the signal slot
     *
     * @return array|mixed
     * @throws InvalidSlotException
     * @throws InvalidSlotReturnException
     */
    protected function emitActionSignal(string $className, string $signalName, array $signalArguments)
    {
        return $this->signalSlotDispatcher->dispatch($className, $signalName, $signalArguments)[0];
    }
}
