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
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Queue page indexer frontend helper base class implementing common
 * functionality.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
abstract class AbstractFrontendHelper implements FrontendHelper, SingletonInterface
{
    /**
     * Index Queue page indexer request.
     */
    protected ?PageIndexerRequest $request = null;

    /**
     * Index Queue page indexer response.
     */
    protected ?PageIndexerResponse $response = null;

    /**
     * Singleton instance variable for indication of indexing request.
     */
    protected bool $isActivated = false;

    /**
     * The action a frontend helper executes.
     */
    protected string $action;

    protected ?SolrLogManager $logger = null;

    /**
     * Starts the execution of a frontend helper.
     *
     * @param PageIndexerRequest $request Page indexer request
     * @param PageIndexerResponse $response Page indexer response
     */
    public function processRequest(
        PageIndexerRequest $request,
        PageIndexerResponse $response
    ): void {
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
    public function deactivate(): void
    {
        $this->isActivated = false;
        $this->response->addActionResult($this->action, $this->getData());
    }
}
