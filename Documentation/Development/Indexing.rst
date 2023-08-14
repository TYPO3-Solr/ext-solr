.. This file will be replaced from solrfluid later

========
Indexing
========

This section describes the possibilities to extend page indexing in EXT:solr with custom code via PSR-14 events.

Page Indexing
=============

There are several points to extend the Page Indexer class and register own classes that are used during the indexing.

BeforePageDocumentIsProcessedForIndexingEvent
---------------------------------------------

Registered Event Listeners can be used to add additional documents to Solr when a page gets indexed.

Registration of an event listener in your extension's :file:`Services.yaml`:

..  code-block:: yaml

    MyVendor\MyPackage\EventListeners\MyEventListener:
      tags:
        - name: event.listener
          identifier: 'my-package/modify-documents'

The corresponding event listener class:

..  code-block:: php

    use ApacheSolrForTypo3\Solr\Event\Indexing\BeforePageDocumentIsProcessedForIndexingEvent;
    use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

    class MyEventListener {

        public function __invoke(BeforePageDocumentIsProcessedForIndexingEvent $event): void
        {
            $additionalDocument = new Document();
            $event->addDocuments([$additionalDocument]);
        }
    }

For other records than pages, the PSR-14 Event :php:class:`ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentIsProcessedForIndexingEvent` can be used.

AfterPageDocumentIsCreatedForIndexingEvent
------------------------------------------

Registered event listeners can be used to replace/substitute a Solr document of a page.

Registration of an event listener in your extension's :file:`Services.yaml`:

..  code-block:: yaml

    MyVendor\MyPackage\EventListeners\MyEventListener:
      tags:
        - name: event.listener
          identifier: 'my-package/modify-page'

The corresponding event listener class:

..  code-block:: php

    use ApacheSolrForTypo3\Solr\Event\Indexing\AfterPageDocumentIsCreatedForIndexingEvent;

    class MyEventListener {

        public function __invoke(AfterPageDocumentIsCreatedForIndexingEvent $event): void
        {
            $event->setDocument($myCustomDocument);
        }
    }



BeforeDocumentsAreIndexedEvent
------------------------------

Registered Event Listeners can be used to process Solr documents (pages and records) before they are added to index.

Registration of an event listener in your extension's :file:`Services.yaml`:

..  code-block:: yaml

    MyVendor\MyPackage\EventListeners\MyEventListener:
      tags:
        - name: event.listener
          identifier: 'my-package/modify-documents'

The corresponding event listener class:

..  code-block:: php

    use ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentsAreIndexedEvent;

    class MyEventListener {

        public function __invoke(BeforeDocumentsAreIndexedEvent $event): void
        {
            foreach ($event->getDocuments() as $document) {
               $document->addField('my_custom_field', 'my_custom_value');
            }
        }
    }


Independent indexer
===================

If external data should be indexed or the RecordIndexer is not required, it is possible to fill the index with an extension as well. The class can then be called e.g. by a CLI call.

.. code-block:: php

   <?php

   namespace Vendor\ExtensionName\Import;

   use ApacheSolrForTypo3\Solr\ConnectionManager;
   use ApacheSolrForTypo3\Solr\Domain\Site\SiteRepository;
   use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;
   use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
   use TYPO3\CMS\Core\Utility\GeneralUtility;

   class Indexer
   {

       protected ConnectionManager $connectionManager;

       public function __construct()
       {
           $this->connectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
       }

       /**
        * Send data to Solr index
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
           $connection->getWriteService()->addDocuments($documents);
       }


       /**
        * Remove all from index
        *
        * @throws \ApacheSolrForTypo3\Solr\NoSolrConnectionFoundException
        */
       public function clearIndex() {
           $connections = $this->connectionManager->getAllConnections();
           foreach ($connections as $connectionLanguage => $connection) {
               /** @var SolrConnection */
               $connection->getWriteService()->deleteByType('custom_type');
           }
       }

       /**
        * Create a Solr document which then is sent to Solr
        *
        * @param array $row
        * @param int $pageId
        * @return Document
        */
       protected function createDocument(array $row, int $pageId): Document
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
        * @return Document A basic Solr document
        */
       protected function getBaseDocument(array $itemRecord, int $rootPageId): Document
       {
           $siteRepository = GeneralUtility::makeInstance(SiteRepository::class);
           $site = $siteRepository->getSiteByRootPageId($rootPageId);

           /** @var Document $document */
           $document = GeneralUtility::makeInstance(Document::class);

           // required fields
           $document->setField('id', 'custom_type_' . $itemRecord['uid']);
           $document->setField('variantId', 'custom_type' . $itemRecord['uid']);
           $document->setField('type', 'custom_type');
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


