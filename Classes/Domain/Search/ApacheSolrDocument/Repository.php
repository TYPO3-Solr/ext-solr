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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument;

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\DocumentEscapeService;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Util;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Repository
 *
 * Purpose: TYPO3 BE INFO module :: Index Documents tab
 */
class Repository implements SingletonInterface
{
    protected ?Search $search = null;

    protected DocumentEscapeService $documentEscapeService;

    protected TypoScriptConfiguration $typoScriptConfiguration;

    protected QueryBuilder $queryBuilder;

    /**
     * Repository constructor.
     */
    public function __construct(
        DocumentEscapeService $documentEscapeService = null,
        TypoScriptConfiguration $typoScriptConfiguration = null,
        QueryBuilder $queryBuilder = null
    ) {
        $this->typoScriptConfiguration = $typoScriptConfiguration ?? Util::getSolrConfiguration();
        $this->documentEscapeService = $documentEscapeService ?? GeneralUtility::makeInstance(DocumentEscapeService::class, $typoScriptConfiguration);
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(QueryBuilder::class, $this->typoScriptConfiguration);
    }

    /**
     * Returns firs found {@link Document} for current page by given language id.
     */
    public function findOneByPageIdAndByLanguageId($pageId, $languageId): Document|false
    {
        $documentCollection = $this->findByPageIdAndByLanguageId($pageId, $languageId);
        return reset($documentCollection);
    }

    /**
     * Returns all found \ApacheSolrForTypo3\Solr\System\Solr\Document\Document[] by given page id and language id.
     * Returns empty array if nothing found, e.g. if no language or no page(or no index for page) is present.
     *
     * @return Document[]
     *
     * @throws DBALException
     */
    public function findByPageIdAndByLanguageId(int $pageId, int $languageId): array
    {
        try {
            $this->initializeSearch($pageId, $languageId);
            $pageQuery = $this->queryBuilder->buildPageQuery($pageId);
            $response = $this->search->search($pageQuery, 0, 10000);
        } catch (NoSolrConnectionFoundException|SolrCommunicationException) {
            return [];
        }
        $data = $response->getParsedData();
        // @extensionScannerIgnoreLine
        return $this->documentEscapeService->applyHtmlSpecialCharsOnAllFields($data->response->docs ?? []);
    }

    /**
     * @return Document[]
     *
     * @throws DBALException
     */
    public function findByTypeAndPidAndUidAndLanguageId(
        string $type,
        int $uid,
        int $pageId,
        int $languageId
    ): array {
        try {
            $this->initializeSearch($pageId, $languageId);
            $recordQuery = $this->queryBuilder->buildRecordQuery($type, $uid, $pageId);
            $response = $this->search->search($recordQuery, 0, 10000);
        } catch (NoSolrConnectionFoundException|SolrCommunicationException) {
            return [];
        }
        $data = $response->getParsedData();
        // @extensionScannerIgnoreLine
        return $this->documentEscapeService->applyHtmlSpecialCharsOnAllFields($data->response->docs ?? []);
    }

    /**
     * Initializes Search for given language
     *
     * @throws DBALException
     * @throws NoSolrConnectionFoundException
     */
    protected function initializeSearch(int $pageId, int $languageId = 0): void
    {
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $solrConnection = $connectionManager->getConnectionByPageId($pageId, $languageId);

        $this->search = $this->getSearch($solrConnection);
    }

    /**
     * Retrieves an instance of the Search object.
     */
    protected function getSearch(SolrConnection $solrConnection): Search
    {
        return  GeneralUtility::makeInstance(Search::class, $solrConnection);
    }
}
