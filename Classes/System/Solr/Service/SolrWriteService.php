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

namespace ApacheSolrForTypo3\Solr\System\Solr\Service;

use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use ApacheSolrForTypo3\Solr\System\Solr\ResponseAdapter;
use Solarium\QueryType\Extract\Query;
use Solarium\QueryType\Update\Result;
use Throwable;

/**
 * Class SolrWriteService
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SolrWriteService extends AbstractSolrService
{
    const EXTRACT_SERVLET = 'update/extract';

    /**
     * Performs a content and meta data extraction request.
     *
     * @param Query $query An extraction query
     * @return array An array containing the extracted content [0] and metadata [1]
     */
    public function extractByQuery(Query $query): array
    {
        try {
            $response = $this->createAndExecuteRequest($query);
            return [$response->file, $response->file_metadata];
        } catch (Throwable $e) {
            $param = $query->getRequestBuilder()->build($query)->getParams();
            $this->logger->log(
                SolrLogManager::ERROR,
                'Extracting text and meta data through Solr Cell over HTTP POST',
                [
                    'query' => (array)$query,
                    'parameters' => $param,
                    'file' => $query->getFile(),
                    'query url' => self::EXTRACT_SERVLET,
                    'exception' => $e->getMessage(),
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
    public function deleteByType(string $type, bool $commit = true)
    {
        $this->deleteByQuery('type:' . trim($type));

        if ($commit) {
            $this->commit(false, false);
        }
    }

    /**
     * Create the delete-query, which is document based on a query and submit it
     *
     * @param string $rawQuery Expected to be utf-8 encoded
     * @return ResponseAdapter
     */
    public function deleteByQuery(string $rawQuery): ResponseAdapter
    {
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
    public function addDocuments(array $documents): ResponseAdapter
    {
        $update = $this->client->createUpdate();
        $update->addDocuments($documents);
        return $this->createAndExecuteRequest($update);
    }

    /**
     * Send a commit command.  Will be synchronous unless both wait parameters are set to false.
     *
     * @param bool $expungeDeletes Defaults to false, merge segments with deletes away
     * @param bool $waitSearcher Defaults to true, block until a new searcher is opened and registered as the main query searcher, making the changes visible
     * @return ResponseAdapter
     */
    public function commit(bool $expungeDeletes = false, bool $waitSearcher = true): ResponseAdapter
    {
        $update = $this->client->createUpdate();
        $update->addCommit(false, $waitSearcher, $expungeDeletes);
        return $this->createAndExecuteRequest($update);
    }

    /**
     * Optimize the solr index
     *
     * @return Result
     */
    public function optimizeIndex(): Result
    {
        $update = $this->client->createUpdate();
        $update->addOptimize(true, false, 5);
        return $this->client->update($update);
    }
}
