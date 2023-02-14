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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Dispatches the actions requested to the matching frontend helpers.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Dispatcher
{
    /**
     * Frontend helper manager.
     *
     * @var Manager
     */
    protected $frontendHelperManager;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->frontendHelperManager = GeneralUtility::makeInstance(Manager::class);
    }

    /**
     * Takes the request's actions and hands them of to the according frontend
     * helpers.
     *
     * @param PageIndexerRequest $request The request to dispatch
     * @param PageIndexerResponse $response The request's response
     */
    public function dispatch(
        PageIndexerRequest $request,
        PageIndexerResponse $response
    ) {
        $actions = $request->getActions();

        foreach ($actions as $action) {
            $frontendHelper = $this->frontendHelperManager->resolveAction($action);
            $frontendHelper->activate();
            $frontendHelper->processRequest($request, $response);
        }
    }

    /**
     * Sends a shutdown signal to all activated frontend helpers.
     */
    public function shutdown()
    {
        $frontendHelpers = $this->frontendHelperManager->getActivatedFrontendHelpers();

        foreach ($frontendHelpers as $frontendHelper) {
            /** @var FrontendHelper $frontendHelper */
            $frontendHelper->deactivate();
        }
    }
}
