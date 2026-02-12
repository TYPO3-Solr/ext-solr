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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\FrontendHelper;
use ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\Manager;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Checks for Index Queue page indexer requests and handles the actions
 * requested by them.
 *
 * This is added in the PSR-7 Frontend Request as "solr.pageIndexingInstructions" attribute
 */
class PageIndexerRequestHandler
{
    /**
     * Index Queue page indexer request.
     */
    protected PageIndexerRequest $request;

    protected Manager $frontendHelperManager;

    /**
     * @var FrontendHelper[]
     */
    protected array $frontendHelpers = [];

    public function __construct(Manager $manager)
    {
        $this->frontendHelperManager = $manager;
    }

    /**
     * Authenticates the request, runs the frontend helpers defined by the
     * request, and registers its own shutdown() method for execution at a later stage
     * when the response is available.
     */
    public function initialize(PageIndexerRequest $request): void
    {
        $actions = $request->getActions();

        foreach ($actions as $action) {
            $frontendHelper = $this->frontendHelperManager->resolveAction($action);
            $frontendHelper->activate($request);
            $this->frontendHelpers[] = $frontendHelper;

            if ($request->getParameter('loggingEnabled')) {
                $logger = GeneralUtility::makeInstance(SolrLogManager::class, get_class($frontendHelper));
                $logger->info(
                    'Page indexer request received',
                    [
                        'request' => (array)$request,
                    ],
                );
            }
        }
    }

    /**
     * Completes the Index Queue page indexer request and returns the response
     * with the collected results.
     */
    public function shutdown(PageIndexerRequest $request): PageIndexerResponse
    {
        $indexerResponse = GeneralUtility::makeInstance(PageIndexerResponse::class);
        $indexerResponse->setRequestId($request->getRequestId());
        foreach ($this->frontendHelpers as $frontendHelper) {
            $frontendHelper->deactivate($indexerResponse);
        }
        return $indexerResponse;
    }
}
