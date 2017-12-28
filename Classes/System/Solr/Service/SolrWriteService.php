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

use ApacheSolrForTypo3\Solr\ExtractingQuery;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;

/**
 * Class SolrReadService
 * @package ApacheSolrForTypo3\System\Solr\Service
 */
class SolrWriteService extends AbstractSolrService
{
    /**
     * Performs a content and meta data extraction request.
     *
     * @param ExtractingQuery $query An extraction query
     * @return array An array containing the extracted content [0] and meta data [1]
     */
    public function extractByQuery(ExtractingQuery $query)
    {
        $headers = [
            'Content-Type' => 'multipart/form-data; boundary=' . $query->getMultiPartPostDataBoundary()
        ];

        try {
            $response = $this->requestServlet(
                self::EXTRACT_SERVLET,
                $query->getQueryParameters(),
                'POST',
                $headers,
                $query->getRawPostFileData()
            );
        } catch (\Exception $e) {
            $this->logger->log(
                SolrLogManager::ERROR,
                'Extracting text and meta data through Solr Cell over HTTP POST',
                [
                    'query' => (array)$query,
                    'parameters' => $query->getQueryParameters(),
                    'file' => $query->getFile(),
                    'headers' => $headers,
                    'query url' => self::EXTRACT_SERVLET,
                    'exception' => $e->getMessage()
                ]
            );
        }

        return [
            $response->extracted,
            (array)$response->extracted_metadata
        ];
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
            $this->commit(false, false, false);
        }
    }

    /**
     * Raw Delete Method. Takes a raw post body and sends it to the update service. Body should be
     * a complete and well formed "delete" xml document
     *
     * @param string $rawPost Expected to be utf-8 encoded xml document
     * @param float|int $timeout Maximum expected duration of the delete operation on the server (otherwise, will throw a communication exception)
     * @return \Apache_Solr_Response
     */
    public function delete($rawPost, $timeout = 3600)
    {
        $response = $this->_sendRawPost($this->_updateUrl, $rawPost, $timeout);
        $this->logger->log(SolrLogManager::NOTICE, 'Delete Query sent.', ['query' => $rawPost, 'query url' => $this->_updateUrl, 'response' => (array)$response]);

        return $response;
    }

}