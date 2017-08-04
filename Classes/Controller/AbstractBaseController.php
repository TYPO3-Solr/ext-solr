<?php
namespace ApacheSolrForTypo3\Solr\Controller;

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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager as SolrConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Service\ConfigurationService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Service\TypoScriptService;
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
     * @var ContentObjectRenderer
     */
    private $contentObjectRenderer;

    /**
     * @var TypoScriptFrontendController
     */
    protected $typoScriptFrontendController;

    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * @var SolrConfigurationManager
     */
    private $solrConfigurationManager;

    /**
     * The configuration is private if you need it please get it from the controllerContext.
     *
     * @var TypoScriptConfiguration
     */
    protected $typoScriptConfiguration;

    /**
     * @var \ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext
     */
    protected $controllerContext;

    /**
     * @var \ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService
     */
    protected $searchService;

    /**
     * @var bool
     */
    protected $resetConfigurationBeforeInitialize = true;

    /**
     * @param \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager
     * @return void
     */
    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager)
    {
        $this->configurationManager = $configurationManager;
        $this->contentObjectRenderer = $this->configurationManager->getContentObject();
    }

    /**
     * @param \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer $contentObjectRenderer
     */
    public function setContentObjectRenderer($contentObjectRenderer)
    {
        $this->contentObjectRenderer = $contentObjectRenderer;
    }

    /**
     * @return \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    public function getContentObjectRenderer()
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
    public function setResetConfigurationBeforeInitialize($resetConfigurationBeforeInitialize)
    {
        $this->resetConfigurationBeforeInitialize = $resetConfigurationBeforeInitialize;
    }

    /**
     * Initialize the controller context
     *
     * @return \TYPO3\CMS\Extbase\Mvc\Controller\ControllerContext ControllerContext to be passed to the view
     * @api
     */
    protected function buildControllerContext()
    {
        /** @var $controllerContext \ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext */
        $controllerContext = $this->objectManager->get(SolrControllerContext::class);
        $controllerContext->setRequest($this->request);
        $controllerContext->setResponse($this->response);
        if ($this->arguments !== null) {
            $controllerContext->setArguments($this->arguments);
        }
        $controllerContext->setUriBuilder($this->uriBuilder);

        $controllerContext->setTypoScriptConfiguration($this->typoScriptConfiguration);

        return $controllerContext;
    }

    /**
     * Initialize action
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

        $this->objectManager->get(ConfigurationService::class)
            ->overrideConfigurationWithFlexFormSettings(
                $this->contentObjectRenderer->data['pi_flexform'],
                $this->typoScriptConfiguration
            );

        parent::initializeAction();
        $this->typoScriptFrontendController = $GLOBALS['TSFE'];
        $this->initializeSettings();
        $this->initializeSearch();
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
     */
    protected function initializeSearch()
    {
        /** @var \ApacheSolrForTypo3\Solr\ConnectionManager $solrConnection */
        $solrConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId($this->typoScriptFrontendController->id, $this->typoScriptFrontendController->sys_language_uid, $this->typoScriptFrontendController->MP);
        $search = GeneralUtility::makeInstance(Search::class, $solrConnection);

        $this->searchService = GeneralUtility::makeInstance(SearchResultSetService::class, $this->typoScriptConfiguration, $search);
    }
}
