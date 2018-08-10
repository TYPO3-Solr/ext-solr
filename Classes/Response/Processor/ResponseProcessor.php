<?php
namespace ApacheSolrForTypo3\Solr\Response\Processor;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

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
