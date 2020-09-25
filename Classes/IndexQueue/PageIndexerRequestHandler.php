<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue;

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
