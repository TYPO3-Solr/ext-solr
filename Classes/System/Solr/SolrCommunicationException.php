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

namespace ApacheSolrForTypo3\Solr\System\Solr;

/**
 * This exception or a more specific one should be thrown when the is an error in the communication with the solr server.
 */
class SolrCommunicationException extends \RuntimeException {

    /**
     * @var ResponseAdapter
     */
    protected $solrResponse;

    /**
     * @return ResponseAdapter
     */
    public function getSolrResponse(): ResponseAdapter
    {
        return $this->solrResponse;
    }

    /**
     * @param ResponseAdapter $solrResponse
     */
    public function setSolrResponse(ResponseAdapter $solrResponse)
    {
        $this->solrResponse = $solrResponse;
    }
}
