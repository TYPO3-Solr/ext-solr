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

!!! Upgrade to Apache Solr 9.3.0
--------------------------------

This release requires Apache Solr v 9.3.0+.

**Note**: On third party installations enabling stream feature via the ENV vars or system properties is required.

Following variables must be set in solr.in.sh file or in Solr system props:
* `SOLR_ENABLE_REMOTE_STREAMING=true`
* `SOLR_ENABLE_STREAM_BODY=true`

For more information see:
* https://solr.apache.org/guide/solr/latest/upgrade-notes/major-changes-in-solr-9.html#security
* https://issues.apache.org/jira/browse/SOLR-14853


Reworked Search Query Component System
--------------------------------------

The Search Component system, which is used to enrich the search query (e.g.
by faceting, boosting, debug analysis), has been completely reworked by
utilizing the PSR-14 event system.

At the same time the Search Query Modifiers have been merged into the
Query Component systems.

All built-in components are now reworked and utilize the
:php:`ApacheSolrForTypo3\Solr\Event\Search\AfterSearchQueryHasBeenPreparedEvent`
PSR-14 event.

The interface :php:`ApacheSolrForTypo3\Solr\Domain\Search\SearchRequestAware` has been removed.

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchQuery']`
and the interfaces :php:`ApacheSolrForTypo3\Solr\Query\Modifier` as well
as :php:`ApacheSolrForTypo3\Solr\Search\QueryAware` and :php:`ApacheSolrForTypo3\Solr\Search\SearchAware`
have been removed. The modifiers have been merged into Components.

Registration does not happen in `ext_localconf.php` anymore via `ApacheSolrForTypo3\Solr\Search\SearchComponentManager`
which has been removed, but now happens in :file:`Configuration/Services.yaml`
as documented in TYPO3 Core's PSR-14 Registration API.

Related hooks around this system have been moved to PSR-14 events as well:
* :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['beforeSearch']` has
  been replaced by :php:`ApacheSolrForTypo3\Solr\Event\Search\AfterInitialSearchResultSetHasBeenCreatedEvent`
* :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch']` has been
  been replaced by :php:`ApacheSolrForTypo3\Solr\Event\Search\AfterSearchHasBeenExecutedEvent`


SignalSlots replaced by PSR-14 events
-------------------------------------

The previously available Extbase Signals have been removed from EXT:solr in favor of PSR-14 Events.

* The signal :php:`ApacheSolrForTypo3\Solr\Domain\Index\IndexService::beforeIndexItems`
  has been replaced by :php:`ApacheSolrForTypo3\Solr\Event\Indexing\BeforeItemsAreIndexedEvent`
* The signal :php:`ApacheSolrForTypo3\Solr\Domain\Index\IndexService::beforeIndexItem`
  has been replaced by :php:`ApacheSolrForTypo3\Solr\Event\Indexing\BeforeItemIsIndexedEvent`
* The signal :php:`ApacheSolrForTypo3\Solr\Domain\Index\IndexService::afterIndexItem`
  has been replaced by :php:`ApacheSolrForTypo3\Solr\Event\Indexing\AfterItemHasBeenIndexedEvent`
* The signal :php:`ApacheSolrForTypo3\Solr\Domain\Index\IndexService::afterIndexItems`
  has been replaced by :php:`ApacheSolrForTypo3\Solr\Event\Indexing\AfterItemsHaveBeenIndexedEvent`
* The signal :php:`ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\Options\OptionFacetParser::optionsParsed`
  has been replaced by :php:`ApacheSolrForTypo3\Solr\Event\Parser\AfterFacetIsParsedEvent`
* The signal :php:`ApacheSolrForTypo3\Solr\Controller\SearchController::resultsAction`
  has been replaced by :php:`ApacheSolrForTypo3\Solr\Event\Search\BeforeSearchResultIsShownEvent`
* The signal :php:`ApacheSolrForTypo3\Solr\Controller\SearchController::formAction`
  has been replaced by :php:`ApacheSolrForTypo3\Solr\Event\Search\BeforeSearchFormIsShownEvent`
* The signal :php:`ApacheSolrForTypo3\Solr\Controller\SearchController::frequentlySearchedAction`
  has been replaced by :php:`ApacheSolrForTypo3\Solr\Event\Search\AfterFrequentlySearchHasBeenExecutedEvent`
* The signal :php:`ApacheSolrForTypo3\Solr\Controller\SearchController::beforeSearch`
  has been removed (see the new PSR-14 events below)

Hooks replaced by PSR-14 events
-------------------------------

The previously available hooks and their respective interfaces have been removed from EXT:solr.

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageAddDocuments']` and its
interface :php:`ApacheSolrForTypo3\Solr\AdditionalPageIndexer` are now superseded
by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\Indexing\BeforePageDocumentIsProcessedForIndexingEvent`.

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifyVariantId']` and its
interface :php:`ApacheSolrForTypo3\Solr\Variants\IdModifier` are now superseded
by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\Variants\AfterVariantIdWasBuiltEvent`.

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['preAddModifyDocuments']` and its
interface :php:`ApacheSolrForTypo3\Solr\PageIndexerDocumentsModifier` are now superseded
by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentIsProcessedForIndexingEvent`.

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments']` and its
interface :php:`ApacheSolrForTypo3\Solr\AdditionalIndexQueueItemIndexer` are now superseded
by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\Indexing\BeforeDocumentsAreIndexedEvent`.

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['Indexer']['indexPageSubstitutePageDocument']` and its
interface :php:`ApacheSolrForTypo3\Solr\SubstitutePageIndexer` are now superseded
by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\Indexing\AfterPageDocumentIsCreatedForIndexingEvent`.

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueInitialization']` and its
interface :php:`ApacheSolrForTypo3\Solr\IndexQueue\InitializationPostProcessor` are now superseded
by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\IndexQueue\AfterIndexQueueHasBeenInitializedEvent`

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessFetchRecordsForIndexQueueItem']` is now superseded
by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\IndexQueue\AfterRecordsForIndexQueueItemsHaveBeenRetrievedEvent`

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueuePageIndexer']['dataUrlModifier']`
and the according interface :php:`ApacheSolrForTypo3\Solr\IndexQueue\PageIndexerDataUrlModifier`
is now superseded by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\Indexing\AfterFrontendPageUriForIndexingHasBeenGeneratedEvent`

The hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['postProcessIndexQueueUpdateItem']`
is now superseded by the PSR-14 event :php:`ApacheSolrForTypo3\Solr\Event\Indexing\AfterIndexQueueItemHasBeenMarkedForReindexingEvent`

PSR-14 events renamed
---------------------

Previous PSR-14 events have been renamed to be consistent with other PSR-14 Events in EXT:solr.

* :php:`ApacheSolrForTypo3\Solr\Event\Routing\PostProcessUriEvent` is now named :php:`ApacheSolrForTypo3\Solr\Event\Routing\AfterUriIsProcessedEvent`
* :php:`ApacheSolrForTypo3\Solr\Event\Routing\BeforeProcessCachedVariablesEvent` is now named :php:`ApacheSolrForTypo3\Solr\Event\Routing\BeforeCachedVariablesAreProcessedEvent`
* :php:`ApacheSolrForTypo3\Solr\Event\Routing\BeforeReplaceVariableInCachedUrlEvent` is now named :php:`ApacheSolrForTypo3\Solr\Event\Routing\BeforeVariableInCachedUrlAreReplacedEvent`

!!! Shortcut pages not indexed anymore
--------------------------------------

Currently there is no important reason to index the shortcut pages,
because the target pages are indexed as expected and the shortcuts are 307-redirected to their targets.
So contents can be found in search results as expected.

!!! Deprecated Node class removed
---------------------------------

Former EXT:solr versions used an own node implementation for Solr endpoints, this implementation (\ApacheSolrForTypo3\Solr\System\Solr\Node) is now removed in favor of the Endpoint implementation of Solarium.

If you've used this class or the SolrConnection directly, you have to adapt your PHP code:
- use \Solarium\Core\Client\Endpoint instead of \ApacheSolrForTypo3\Solr\System\Solr\Node
- call \ApacheSolrForTypo3\Solr\System\Solr\SolrConnection->getEndpoint() instead of \ApacheSolrForTypo3\Solr\System\Solr\SolrConnection\getNode(),
  method will return Solarium Endpoint
- Node could be converted to string to get the core base URI, getCoreBaseUri() can be used instead. 

Note: With dropping the Node implementation we also dropped the backwards compatibility that allows to define the Solr path segment "/solr" within "solr_path_read" or "solr_path_write". Be sure your configuration doesn't contain this path segment!


Frontend Helper Changes
-----------------------

The FrontendHelper logic revolving around PageIndexer has been reduced to
a minimum by only having two methods available:

* :php:`ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\FrontendHelper::activate()` - used to register hooks and PSR-14 event listeners
* :php:`ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\FrontendHelper::deactivate(PageIndexerResponse $response)` - used to populate data into the PageIndexerResponse object

The actual PageIndexerRequest object is now available as a property of TYPO3's
Request object as attribute named "solr.pageIndexingInstructions".

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


