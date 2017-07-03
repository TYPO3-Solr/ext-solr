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
 *  the Free Software Foundation; either version 2 of the License, or
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
use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
use ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\SolrService;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ApacheSolrDocumentRepository uses connection to Solr Server
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
     * Returns firs found Apache_Solr_Document for current page by given language id.
     *
     * @param $languageId
     * @return \Apache_Solr_Document|false
     */
    public function findOneByPageIdAndByLanguageId($pageId, $languageId)
    {
        $documentCollection = $this->findByPageIdAndByLanguageId($pageId, $languageId);
        return reset($documentCollection);
    }

    /**
     * Returns all found Apache_Solr_Document[] by given page id and language id.
     * Returns empty array if nothing found, e.g. if no language or no page(or no index for page) is present.
     *
     * @param int $pageId
     * @param int $languageId
     * @return \Apache_Solr_Document[]
     */
    public function findByPageIdAndByLanguageId($pageId, $languageId)
    {
        try {
            $this->initializeSearch($pageId, $languageId);
            $this->search->search($this->getQueryForPage($pageId), 0, 10000);
        } catch (NoSolrConnectionFoundException $exception) {
            return [];
        }
        return $this->search->getResultDocumentsEscaped();
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
     * Returns Query for Saearch which finds document for given page.
     * Note: The Connection is per language as recommended in ext-solr docs.
     *
     * @return Query
     */
    protected function getQueryForPage($pageId)
    {
            /** @var $siteRepository SiteRepository */
        $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
        $site = $siteRepository->getSiteByPageId($pageId);
        /* @var Query $query */
        $query = GeneralUtility::makeInstance(Query::class, '');
        $query->setQueryType('standard');
        $query->useRawQueryString(true);
        $query->setQueryString('*:*');
        $query->getFilters()->add('(type:pages AND uid:' . $pageId . ') OR (*:* AND pid:' . $pageId . ' NOT type:pages)');
        $query->getFilters()->add('siteHash:' . $site->getSiteHash());
        $query->getReturnFields()->add('*');
        $query->setSorting('type asc, title asc');

        return $query;
    }

    /**
     * Retrieves an instance of the Search object.
     *
     * @param SolrService $solrConnection
     * @return Search
     */
    protected function getSearch($solrConnection)
    {
        return  GeneralUtility::makeInstance(Search::class, $solrConnection);
    }
}
