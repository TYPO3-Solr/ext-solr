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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Dispatcher;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Checks for Index Queue page indexer requests and handles the actions
 * requested by them.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class PageIndexerRequestHandler implements SingletonInterface
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
     * Index Queue page indexer frontend helper dispatcher.
     *
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * Constructor.
     *
     * Initializes request, response, and dispatcher.
     * @param string|null $jsonEncodedParameters
     */
    public function __construct(string $jsonEncodedParameters = null)
    {
        $this->dispatcher = GeneralUtility::makeInstance(Dispatcher::class);
        $this->request = GeneralUtility::makeInstance(PageIndexerRequest::class, /** @scrutinizer ignore-type */ $jsonEncodedParameters);
        $this->response = GeneralUtility::makeInstance(PageIndexerResponse::class);
        $this->response->setRequestId($this->request->getRequestId());
    }


    /**
     * Authenticates the request, runs the frontend helpers defined by the
     * request, and registers its own shutdown() method for execution at
     * hook_eofe in tslib/class.tslib_fe.php.
     *
     * @return void
     */
    public function run()
    {
        $this->dispatcher->dispatch($this->request, $this->response);
    }

    /**
     * Completes the Index Queue page indexer request and returns the response
     * with the collected results.
     *
     * @return void
     */
    public function shutdown()
    {
        $this->dispatcher->shutdown();
    }

    /**
     * Gets the Index Queue page indexer request.
     *
     * @return PageIndexerRequest
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Gets the Index Queue page indexer response.
     *
     * @return PageIndexerResponse
     */
    public function getResponse()
    {
        return $this->response;
    }
}
