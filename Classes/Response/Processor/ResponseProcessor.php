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

namespace ApacheSolrForTypo3\Solr\Response\Processor;

use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;

/**
 * ResponseProcessor interface, allows to process search responses.
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface ResponseProcessor
{

    /**
     * Processes a query and its response after searching for that query.
     *
     * @param Query $query The query that has been searched for.
     * @param ResponseAdapter $response The response for the last query.
     * @return void
     */
    public function processResponse(
        Query $query,
        ResponseAdapter $response
    );
}
