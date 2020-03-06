<?php
namespace ApacheSolrForTypo3\Solr\Widget;

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
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\Util;
use ApacheSolrForTypo3\Solr\Widget\WidgetRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\Widget\AbstractWidgetController as CoreAbstractWidgetController;

/**
 * Class AbstractWidgetController
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class AbstractWidgetController extends CoreAbstractWidgetController
{

    /**
     * @var array
     */
    protected $supportedRequestTypes = [WidgetRequest::class];

    /**
     * @var ConfigurationManager
     */
    private $solrConfigurationManager;

    /**
     * @var \ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext
     */
    protected $controllerContext;

    /**
     * @param \ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager
     */
    public function injectSolrConfigurationManager(ConfigurationManager $configurationManager)
    {
        $this->solrConfigurationManager = $configurationManager;
    }

    /**
     * Initialize the controller context
     *
     * @return \ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext ControllerContext to be passed to the view
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
        $typoScriptConfiguration = $this->solrConfigurationManager->getTypoScriptConfiguration();
        $controllerContext->setTypoScriptConfiguration($typoScriptConfiguration);

        $this->setActiveSearchResultSet($controllerContext);

        return $controllerContext;
    }

    /**
     * @param \ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext $controllerContext
     * @return \ApacheSolrForTypo3\Solr\Mvc\Controller\SolrControllerContext
     */
    protected function setActiveSearchResultSet($controllerContext)
    {
        $resultSetService = $this->initializeSearch($controllerContext->getTypoScriptConfiguration());
        $lastResult = $resultSetService->getLastResultSet();
        if (!is_null($lastResult)) {
            $controllerContext->setSearchResultSet($lastResult);
        }

        return $controllerContext;
    }

    /**
     * @param TypoScriptConfiguration $typoScriptConfiguration
     * @return SearchResultSetService
     */
    protected function initializeSearch(TypoScriptConfiguration $typoScriptConfiguration)
    {
        /** @var \ApacheSolrForTypo3\Solr\ConnectionManager $solrConnection */
        $solrConnection = GeneralUtility::makeInstance(ConnectionManager::class)->getConnectionByPageId($GLOBALS['TSFE']->id, Util::getLanguageUid(), $GLOBALS['TSFE']->MP);
        $search = GeneralUtility::makeInstance(Search::class, /** @scrutinizer ignore-type */ $solrConnection);

        return GeneralUtility::makeInstance(
            SearchResultSetService::class,
            /** @scrutinizer ignore-type */ $typoScriptConfiguration,
            /** @scrutinizer ignore-type */ $search
        );
    }
}
