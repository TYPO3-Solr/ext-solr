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

namespace ApacheSolrForTypo3\Solr\Search;

use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;

/**
 * ResponseModifier interface, allows to modify the search response
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface ResponseModifier
{

    /**
     * Modifies the given response and returns the modified response as result
     *
     * @param ResponseAdapter $response The response to modify
     * @return ResponseAdapter The modified response
     */
    public function modifyResponse(ResponseAdapter $response);
}
