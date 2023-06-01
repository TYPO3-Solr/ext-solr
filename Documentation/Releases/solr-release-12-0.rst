.. include:: ../Includes.rst.txt


.. _releases-12-0:

============================
Apache Solr for TYPO3 12.0.0
============================

We are happy to release EXT:solr 12.0.0.
The focus of this release has been on TYPO3 12 LTS compatibility.

New in this release
===================

Support of TYPO3 12 LTS
-----------------------

With EXT:solr 12.0 we provide the support of TYPO3 12 LTS.

Hooks replaced by PSR-14 events
-------------------------------

The previously available hooks and their respective interfaces have been removed from EXT:solr.

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments']` and its
interface :php:`ApacheSolrForTypo3\Solr\AdditionalPageIndexer` are now superseded
by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\Indexing\BeforePageDocumentIsProcessedForIndexingEvent`.

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['preAddModifyDocuments']` and its
interface :php:`ApacheSolrForTypo3\Solr\PageIndexerDocumentsModifier` are now superseded
by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentIsProcessedForIndexingEvent`.

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments']` and its
interface :php:`ApacheSolrForTypo3\Solr\AdditionalIndexQueueItemIndexer` are now superseded
by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentsAreIndexedEvent`.

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']` and its
interface :php:`ApacheSolrForTypo3\Solr\SubstitutePageIndexer` are now superseded
by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\Indexing\AfterPageDocumentIsCreatedForIndexingEvent`.


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* ---- TBD!!!


Also a big thank you to our partners who have already concluded one of our new development participation packages such as Apache Solr EB for TYPO3 12 LTS (Feature), Apache Solr EB for TYPO3 11 LTS (Maintenance)
or Apache Solr EB for TYPO3 10 and 9 ELTS (Extended):


* ---- TBD!!!

How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us by becoming an EB partner:

https://shop.dkd.de/Produkte/Apache-Solr-fuer-TYPO3/

or call:

+49 (0)69 - 2475218 0


