<?php

declare(strict_types=1);

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

namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

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
     * @var PageIndexerRequest|null
     */
    protected ?PageIndexerRequest $request = null;

    /**
     * Index Queue page indexer response.
     *
     * @var PageIndexerResponse|null
     */
    protected ?PageIndexerResponse $response = null;

    /**
     * The action a frontend helper executes.
     */
    protected string $action;

    /**
     * @var SolrLogManager|null
     */
    protected ?SolrLogManager $logger = null;

    /**
     * Disables the frontend output for index queue requests.
     *
     * @param array $parameters Parameters from frontend
     */
    public function disableFrontendOutput(array &$parameters)
    {
        $parameters['enableOutput'] = false;
    }

    /**
     * Disables caching for page generation to get reliable results.
     *
     * @param array $parameters Parameters from frontend
     * @param TypoScriptFrontendController $parentObject TSFE object
     * @noinspection PhpUnused
     */
    public function disableCaching(
        /** @noinspection PhpUnusedParameterInspection */
        array &$parameters,
        TypoScriptFrontendController $parentObject
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
