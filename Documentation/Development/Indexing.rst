.. This file will be replaced from solrfluid later

========
Indexing
========

In this section i describe the possibilities to extend page indexing in EXT:solr with custom code.

Page Indexing
=============

There are several points to extend the Typo3PageIndexer class and register own classes that are used during the indexing.

indexPageAddDocuments
---------------------

Registered classes can be used to add additional documents to solr when a page get's indexed.

Registration with: $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments']
Required Interface: AdditionalPageIndexer


indexPageSubstitutePageDocument
-------------------------------

Registered classes can be used to replace/substitute a Solr document of a page.


Registration with: $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']
Required Interface: SubstitutePageIndexer

indexPagePostProcessPageDocument
--------------------------------

Registered classes can be used to post process a Solr document of a page.

Registration with: $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPagePostProcessPageDocument']
Required Interface: PageDocumentPostProcessor


Independent indexer
===================

If external data should be indexed or the RecordIndexer is not required, it is possible to fill the index with an extension as well. The class can then be called e.g. by a CLI call.

.. code-block:: php

   <?php

   namespace Vendor\ExtensionName\Import;

   use Apache_Solr_Document;
   use ApacheSolrForTypo3\Solr\ConnectionManager;
   use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
   use TYPO3\CMS\Core\Utility\GeneralUtility;

   class Indexer
   {

       /** @var ConnectionManager */
       protected $connectionManager;

       public function __construct()
       {
           $this->connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
       }

       /**
        * Send data to solr index
        *
        * @param array $rows Data to be indexed, e.g. multiple DB rows
        * @param int $pageId root page
        * @param int $language language id
        */
       public function index(array $rows, int $pageId = 1, int $language = 0)
       {
           $documents = [];

           foreach ($rows as $row) {
               $documents[] = $this->createDocument($row, $pageId);
           }

           $connection = $this->connectionManager->getConnectionByPageId($pageId, $language);
           $connection->addDocuments($documents);
       }
       
       
       /**
        * Remove all from index
        *
        * @throws \ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException
        */
       public function clearIndex() {
           $connections = $this->getSolrConnections();
           foreach ($connections as $connectionLanguage => $connection) {
               /** @var ConnectionManager */
               $connection->deleteByType('cutom_type');
           }
       }

       /**
        * Create a solr document which then is sent to solr
        *
        * @param array $row
        * @param int $pageId
        * @return Apache_Solr_Document
        */
       protected function createDocument(array $row, int $pageId): Apache_Solr_Document
       {
           $document = $this->getBaseDocument($row, $pageId);

           $solrFieldMapping = [
               'title' => 'title',
               'summary' => 'abstract',
               'information' => 'content',
               'keywords' => 'keywords',
               'area' => 'area_stringS',
               'category' => 'category_stringS'
           ];

           foreach ($row as $key => $value) {
               if (isset($solrFieldMapping[$key])) {
                   $document->setField($solrFieldMapping[$key], $value);
               }
           }

           // url generation
           $document->setField('url', 'todo'); // custom implementation required

           return $document;
       }

       /**
        * Creates a Solr document with the basic / core fields set already.
        *
        * @param array $itemRecord The record to use to build the base document
        * @param int $rootPageId root page id
        * @return Apache_Solr_Document A basic Solr document
        */
       protected function getBaseDocument(array $itemRecord, int $rootPageId): Apache_Solr_Document
       {
           $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
           $site = $siteRepository->getSiteByRootPageId($rootPageId);

           $document = GeneralUtility::makeInstance(Apache_Solr_Document::class);

           // required fields
           $document->setField('id', 'cutom_type_' . $itemRecord['uid']);
           $document->setField('variantId', 'cutom_type' . $itemRecord['uid']);
           $document->setField('type', 'cutom_type');
           $document->setField('appKey', 'EXT:solr');
           $document->setField('access', ['r:0']);

           // site, siteHash
           $document->setField('site', $site->getDomain());
           $document->setField('siteHash', $site->getSiteHash());

           // uid, pid
           $document->setField('uid', $itemRecord['uid']);
           $document->setField('pid', 1);

           return $document;
       }
   }


