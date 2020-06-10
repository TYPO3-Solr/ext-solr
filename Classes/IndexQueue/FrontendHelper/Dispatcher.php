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
     *
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
     *
     * @return void
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
