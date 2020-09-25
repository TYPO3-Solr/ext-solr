<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Index Queue page indexer frontend helper base class implementing common
 * functionality.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
abstract class AbstractFrontendHelper implements FrontendHelper
{

    /**
     * Index Queue page indexer request.
     *
     * @var PageIndexerRequest
     */
    protected $request;

    /**
     * Index Queue page indexer response.
     *
     * @var PageIndexerResponse
     */
    protected $response;

    /**
     * The action a frontend helper executes.
     */
    protected $action = null;

    /**
     * @var SolrLogManager
     */
    protected $logger = null;

    /**
     * Disables the frontend output for index queue requests.
     *
     * @param array $parameters Parameters from frontend
     */
    public function disableFrontendOutput(&$parameters)
    {
        $parameters['enableOutput'] = false;
    }

    /**
     * Disables caching for page generation to get reliable results.
     *
     * @param array $parameters Parameters from frontend
     * @param TypoScriptFrontendController $parentObject TSFE object
     */
    public function disableCaching(
        /** @noinspection PhpUnusedParameterInspection */
        &$parameters,
        $parentObject
    ) {
        $parentObject->no_cache = true;
    }

    /**
     * Starts the execution of a frontend helper.
     *
     * @param PageIndexerRequest $request Page indexer request
     * @param PageIndexerResponse $response Page indexer response
     */
    public function processRequest(
        PageIndexerRequest $request,
        PageIndexerResponse $response
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->logger = GeneralUtility::makeInstance(SolrLogManager::class, /** @scrutinizer ignore-type */ __CLASS__);

        if ($request->getParameter('loggingEnabled')) {
            $this->logger->log(
                SolrLogManager::INFO,
                'Page indexer request received',
                [
                    'request' => (array)$request,
                ]
            );
        }
    }

    /**
     * Deactivates a frontend helper by unregistering from hooks and releasing
     * resources.
     */
    public function deactivate()
    {
        $this->response->addActionResult($this->action, $this->getData());
    }
}
