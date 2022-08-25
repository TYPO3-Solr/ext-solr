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
use Exception;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Repository
 *
 * Purpose: TYPO3 BE INFO module :: Index Documents tab
 */
class Repository implements SingletonInterface
{
    /**
     * Search
     *
     * @var Search|null
     */
    protected ?Search $search = null;

    /**
     * @var DocumentEscapeService
     */
    protected DocumentEscapeService $documentEscapeService;

    /**
     * @var TypoScriptConfiguration
     */
    protected TypoScriptConfiguration $typoScriptConfiguration;

    /**
     * @var QueryBuilder
     */
    protected QueryBuilder $queryBuilder;

    /**
     * Repository constructor.
     * @param DocumentEscapeService|null $documentEscapeService
     * @param QueryBuilder|null $queryBuilder
     */
    public function __construct(
        DocumentEscapeService $documentEscapeService = null,
        TypoScriptConfiguration $typoScriptConfiguration = null,
        QueryBuilder $queryBuilder = null
    ) {
        $this->typoScriptConfiguration = $typoScriptConfiguration ?? Util::getSolrConfiguration();
        $this->documentEscapeService = $documentEscapeService ?? GeneralUtility::makeInstance(DocumentEscapeService::class, /** @scrutinizer ignore-type */ $typoScriptConfiguration);
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(QueryBuilder::class, /** @scrutinizer ignore-type */ $this->typoScriptConfiguration);
    }

    /**
     * Returns firs found \ApacheSolrForTypo3\Solr\System\Solr\Document\Document for current page by given language id.
     *
     * @param $pageId
     * @param $languageId
     * @return Document|false
     * @throws Exception
     */
    public function findOneByPageIdAndByLanguageId($pageId, $languageId)
    {
        $documentCollection = $this->findByPageIdAndByLanguageId($pageId, $languageId);
        return reset($documentCollection);
    }

    /**
     * Returns all found \ApacheSolrForTypo3\Solr\System\Solr\Document\Document[] by given page id and language id.
     * Returns empty array if nothing found, e.g. if no language or no page(or no index for page) is present.
     *
     * @param int $pageId
     * @param int $languageId
     * @return Document[]
     * @throws Exception
     */
    public function findByPageIdAndByLanguageId(int $pageId, int $languageId): array
    {
        try {
            $this->initializeSearch($pageId, $languageId);
            $pageQuery = $this->queryBuilder->buildPageQuery($pageId);
            $response = $this->search->search($pageQuery, 0, 10000);
        } catch (NoSolrConnectionFoundException|SolrCommunicationException $exception) {
            return [];
        }
        $data = $response->getParsedData();
        // @extensionScannerIgnoreLine
        return $this->documentEscapeService->applyHtmlSpecialCharsOnAllFields($data->response->docs ?? []);
    }

    /**
     * @param string $type
     * @param int $uid
     * @param int $pageId
     * @param int $languageId
     * @return Document[]|array
     * @throws Exception
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
        } catch (NoSolrConnectionFoundException|SolrCommunicationException $exception) {
            return [];
        }
        $data = $response->getParsedData();
        // @extensionScannerIgnoreLine
        return $this->documentEscapeService->applyHtmlSpecialCharsOnAllFields($data->response->docs ?? []);
    }

    /**
     * Initializes Search for given language
     *
     * @param int $pageId
     * @param int $languageId
     * @throws NoSolrConnectionFoundException
     */
    protected function initializeSearch(int $pageId, int $languageId = 0)
    {
        /* @var ConnectionManager $connectionManager */
        $connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
        $solrConnection = $connectionManager->getConnectionByPageId($pageId, $languageId);

        $this->search = $this->getSearch($solrConnection);
    }

    /**
     * Retrieves an instance of the Search object.
     *
     * @param SolrConnection $solrConnection
     * @return Search
     */
    protected function getSearch(SolrConnection $solrConnection): Search
    {
        return  GeneralUtility::makeInstance(Search::class, /** @scrutinizer ignore-type */ $solrConnection);
    }
}
