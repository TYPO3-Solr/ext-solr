<?php
namespace ApacheSolrForTypo3\Solr\System\Solr\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use Solarium\QueryType\Extract\Query;

/**
 * Class SolrWriteService
 */
class SolrWriteService extends AbstractSolrService
{
    const EXTRACT_SERVLET = 'update/extract';

    /**
     * Performs a content and meta data extraction request.
     *
     * @param Query $query An extraction query
     * @return array An array containing the extracted content [0] and meta data [1]
     */
    public function extractByQuery(Query $query)
    {
        try {
            $response = $this->createAndExecuteRequest($query);
            $fileName = basename($query->getFile());
            $metaKey = $fileName . '_metadata';
            return [$response->{$fileName}, (array)$response->{$metaKey}];
        } catch (\Exception $e) {
            $param = $query->getRequestBuilder()->build($query)->getParams();
            $this->logger->log(
                SolrLogManager::ERROR,
                'Extracting text and meta data through Solr Cell over HTTP POST',
                [
                    'query' => (array)$query,
                    'parameters' => $param,
                    'file' => $query->getFile(),
                    'query url' => self::EXTRACT_SERVLET,
                    'exception' => $e->getMessage()
                ]
            );
        }

        return [];
    }

    /**
     * Deletes all index documents of a certain type and does a commit
     * afterwards.
     *
     * @param string $type The type of documents to delete, usually a table name.
     * @param bool $commit Will commit immediately after deleting the documents if set, defaults to TRUE
     */
    public function deleteByType($type, $commit = true)
    {
        $this->deleteByQuery('type:' . trim($type));

        if ($commit) {
            $this->commit(false, false);
        }
    }

    /**
     * Create a delete document based on a query and submit it
     *
     * @param string $rawQuery Expected to be utf-8 encoded
     * @return ResponseAdapter
     */
    public function deleteByQuery($rawQuery) {
        $query = $this->client->createUpdate();
        $query->addDeleteQuery($rawQuery);
        return $this->createAndExecuteRequest($query);
    }

    /**
     * Add an array of Solr Documents to the index all at once
     *
     * @param array $documents Should be an array of \ApacheSolrForTypo3\Solr\System\Solr\Document\Document instances
     * @return ResponseAdapter
     */
    public function addDocuments($documents)
    {
        $update = $this->client->createUpdate();
        $update->addDocuments($documents);
        return $this->createAndExecuteRequest($update);
    }

    /**
     * Send a commit command.  Will be synchronous unless both wait parameters are set to false.
     *
     * @param boolean $expungeDeletes Defaults to false, merge segments with deletes away
     * @param boolean $waitSearcher Defaults to true, block until a new searcher is opened and registered as the main query searcher, making the changes visible
     * @return ResponseAdapter
     */
    public function commit($expungeDeletes = false, $waitSearcher = true)
    {
        $update = $this->client->createUpdate();
        $update->addCommit(false, $waitSearcher, $expungeDeletes);
        return $this->createAndExecuteRequest($update);
    }
}
