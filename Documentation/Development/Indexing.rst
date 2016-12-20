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




