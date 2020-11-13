<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\ApacheSolrDocument;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Rafael Kähm <rafael.kaehm@dkd.de>
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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\Domain\Search\Query\QueryBuilder;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result\Parser\DocumentEscapeService;
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
use ApacheSolrForTypo3\Solr\System\Solr\SolrCommunicationException;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Repository
 */
class Repository implements SingletonInterface
{

    /**
     * Search
     *
     * @var \ApacheSolrForTypo3\Solr\Search
     */
    protected $search;

    /**
     * @var DocumentEscapeService
     */
    protected $documentEscapeService = null;

    /**
     * @var TypoScriptConfiguration|null
     */
    protected $typoScriptConfiguration = null;

    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * Repository constructor.
     * @param DocumentEscapeService|null $documentEscapeService
     * @param QueryBuilder|null $queryBuilder
     */
    public function __construct(DocumentEscapeService $documentEscapeService = null, TypoScriptConfiguration $typoScriptConfiguration = null, QueryBuilder $queryBuilder = null)
    {
        $this->typoScriptConfiguration = $typoScriptConfiguration ?? Util::getSolrConfiguration();
        $this->documentEscapeService = $documentEscapeService ?? GeneralUtility::makeInstance(DocumentEscapeService::class, /** @scrutinizer ignore-type */ $typoScriptConfiguration);
        $this->queryBuilder = $queryBuilder ?? GeneralUtility::makeInstance(QueryBuilder::class, /** @scrutinizer ignore-type */ $this->typoScriptConfiguration);
    }

    /**
     * Returns firs found \ApacheSolrForTypo3\Solr\System\Solr\Document\Document for current page by given language id.
     *
     * @param $languageId
     * @return Document|false
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
     */
    public function findByPageIdAndByLanguageId($pageId, $languageId)
    {
        try {
            $this->initializeSearch($pageId, $languageId);
            $pageQuery = $this->queryBuilder->buildPageQuery($pageId);
            $response = $this->search->search($pageQuery, 0, 10000);
        } catch (NoSolrConnectionFoundException $exception) {
            return [];
        } catch (SolrCommunicationException $exception) {
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
     */
    public function findByTypeAndPidAndUidAndLanguageId($type, $uid, $pageId, $languageId): array
    {
        try {
            $this->initializeSearch($pageId, $languageId);
            $recordQuery = $this->queryBuilder->buildRecordQuery($type, $uid, $pageId);
            $response = $this->search->search($recordQuery, 0, 10000);
        } catch (NoSolrConnectionFoundException $exception) {
            return [];
        } catch (SolrCommunicationException $exception) {
            return [];
        }
        $data = $response->getParsedData();
        // @extensionScannerIgnoreLine
        return $this->documentEscapeService->applyHtmlSpecialCharsOnAllFields($data->response->docs ?? []);
    }

    /**
     * Initializes Search for given language
     *
     * @param int $languageId
     */
    protected function initializeSearch($pageId, $languageId = 0)
    {
        if (!is_int($pageId)) {
            throw new \InvalidArgumentException('Invalid page ID = ' . $pageId, 1487332926);
        }
        if (!is_int($languageId)) { // @todo: Check if lang id is defined and present?
            throw new \InvalidArgumentException('Invalid language ID = ' . $languageId, 1487335178);
        }
        /* @var $connectionManager ConnectionManager */
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
    protected function getSearch($solrConnection)
    {
        return  GeneralUtility::makeInstance(Search::class, /** @scrutinizer ignore-type */ $solrConnection);
    }
}
