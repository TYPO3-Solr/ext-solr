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

namespace ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper;

use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerRequest;
use ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerResponse;

/**
 * Index Queue Frontend Helper interface.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface FrontendHelper
{

    /**
     * Activates a frontend helper by registering for hooks and other
     * resources required by the frontend helper to work.
     */
    public function activate();

    /**
     * Deactivates a frontend helper by unregistering from hooks and releasing
     * resources.
     */
    public function deactivate();

    /**
     * Starts the execution of a frontend helper.
     *
     * @param PageIndexerRequest $request Page indexer request
     * @param PageIndexerResponse $response Page indexer response
     */
    public function processRequest(
        PageIndexerRequest $request,
        PageIndexerResponse $response
    );

    /**
     * Returns the collected data.
     *
     * @return array Collected data.
     */
    public function getData();
}
