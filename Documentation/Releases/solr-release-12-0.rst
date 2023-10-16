.. include:: ../Includes.rst.txt


.. _releases-12-0:

============================
Apache Solr for TYPO3 12.0.0
============================

We are happy to release EXT:solr 12.0.0.
The focus of this release has been on TYPO3 12 LTS compatibility.

Please note that we require at least TYPO3 12.4.3, as this version contains some change `concerning to Fluid <https://github.com/TYPO3-Solr/ext-solr/commit/a528113bf>`_.

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

!!! Changed visibility of ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\PageIndexer methods
-----------------------------------------------------------------------------------------------

For testing purposes some methods of the PageIndexer were defined as public, these methods are now protected. The tests are adapted accordingly, so that there is no need to declare the methods as public.
If you have used one of this methods, you have to adapt your code. Affected methods:
- setupConfiguration
- index
- indexPage

!!! Solr route enhancer disabled by default
-------------------------------------------

EXT:solr offers the possibility to create speaking URLs for Solr facets, but as this feature requires additional configuration and costly processing this feature is now disabled by default.

If you've already used the route enhancer you must set option "enableRouteEnhancer":

:php:`$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr']['enableRouteEnhancer']`


Frontend Helper Changes
-----------------------

The FrontendHelper logic revolving around PageIndexer has been reduced to
a minimum by only having two methods available:

* :php:`ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\FrontendHelper::activate()` - used to register hooks and PSR-14 event listeners
* :php:`ApacheSolrForTypo3\Solr\IndexQueue\FrontendHelper\FrontendHelper::deactivate(PageIndexerResponse $response)` - used to populate data into the PageIndexerResponse object

The actual PageIndexerRequest object is now available as a property of TYPO3's
Request object as attribute named "solr.pageIndexingInstructions".

!!!Complex query in FlexForm filter value
-----------------------------------------

It is now possible to use complex query in FlexForm filter value.
If the value contains space and no special characters, the value is always automatically escaped.

The old behaviour is still working,
so if a string value contains space(s) and no special characters of the solr query parser,
the string is always wrapped with double quotes.
But if the string contains special characters no wrapping happen
special characters are: :php:`+ - && || ! ( ) { } [ ] ^ " ~ * ? : \`

There is some cases where this change can break,
for example if the filter value is something like
:php:`toto AND tata` or :php:`music (rock)` or `my "flow" is`.
Here the wrapping and the escaping of the inner double quote have to be manually updated like this
:php:`"toto AND tata"` or :php:`"music (rock)"` and :php:`"my \"flow\" is"`.

All Changes
===========

- [TASK] TYPO3 dependencies `644ef7ab6 on @2022-10-24 <https://github.com/TYPO3-Solr/ext-solr/commit/644ef7ab6>`_ (thanks to Lars Tode)
- [TASK] Upgrade Solarium `c9a42e8b6 on @2022-10-24 <https://github.com/TYPO3-Solr/ext-solr/commit/c9a42e8b6>`_ (thanks to Lars Tode)
- [TASK] Temporary: Requirement to dev-main `f5e6bd7b4 on @2022-10-24 <https://github.com/TYPO3-Solr/ext-solr/commit/f5e6bd7b4>`_ (thanks to Lars Tode)
- [TASK] Reports: Make status compatible to StatusProviderInterface `2e5d1f664 on @2022-10-24 <https://github.com/TYPO3-Solr/ext-solr/commit/2e5d1f664>`_ (thanks to Lars Tode)
- [TASK] Github build `407a37044 on @2022-10-25 <https://github.com/TYPO3-Solr/ext-solr/commit/407a37044>`_ (thanks to Lars Tode)
- [TASK] Reports: Move registration into Service.yaml `e8d82123d on @2022-10-25 <https://github.com/TYPO3-Solr/ext-solr/commit/e8d82123d>`_ (thanks to Lars Tode)
- [TASK] ViewHelper: Translation ViewHelper `e690ad4b1 on @2022-10-25 <https://github.com/TYPO3-Solr/ext-solr/commit/e690ad4b1>`_ (thanks to Lars Tode)
- [TASK:T12] Fix GitHub - Actions :: basics `ecd4a7b93 on @2022-10-28 <https://github.com/TYPO3-Solr/ext-solr/commit/ecd4a7b93>`_ (thanks to Rafael Kähm)
- [TASK] Adjust dependency injection `7b65c88de on @2023-01-16 <https://github.com/TYPO3-Solr/ext-solr/commit/7b65c88de>`_ (thanks to Lars Tode)
- [TASK:T12] Fix TYPO3 12+ coding standards for 0.7.1+ `75aeab652 on @2023-01-19 <https://github.com/TYPO3-Solr/ext-solr/commit/75aeab652>`_ (thanks to Rafael Kähm)
- [TASK] Replace ObjectManager `b15232138 on @2023-01-16 <https://github.com/TYPO3-Solr/ext-solr/commit/b15232138>`_ (thanks to Lars Tode)
- !!![TASK] Replace signal-slot with event dispatcher `3a1fb2af0 on @2023-01-16 <https://github.com/TYPO3-Solr/ext-solr/commit/3a1fb2af0>`_ (thanks to Lars Tode)
- [TASK:T12] Refactor basic files and method argument hinting `7d81148ea on @2023-01-21 <https://github.com/TYPO3-Solr/ext-solr/commit/7d81148ea>`_ (thanks to Rafael Kähm)
- [TASK:T12] Replace QueryGenerator `1ecbf9e47 on @2023-01-16 <https://github.com/TYPO3-Solr/ext-solr/commit/1ecbf9e47>`_ (thanks to Lars Tode)
- [TASK:T12] move Css folder to public path `0ddc4d8b9 on @2023-01-27 <https://github.com/TYPO3-Solr/ext-solr/commit/0ddc4d8b9>`_ (thanks to Rafael Kähm)
- [TASK:T12] Allow TYPO3 12 dev state `3d726e1bb on @2023-01-27 <https://github.com/TYPO3-Solr/ext-solr/commit/3d726e1bb>`_ (thanks to Rafael Kähm)
- [TASK:T12] Run `rector process` :: basic changes `03136dd02 on @2023-01-27 <https://github.com/TYPO3-Solr/ext-solr/commit/03136dd02>`_ (thanks to Rafael Kähm)
- [TASK:T12] Registration of cObjects for TYPO3 12 LTS `9be3abbb2 on @2023-02-02 <https://github.com/TYPO3-Solr/ext-solr/commit/9be3abbb2>`_ (thanks to Rafael Kähm)
- [FIX] FrontendEnvironment\Tsfe : replace getConfigArray() with getFromCache() `08b846b7c on @2023-02-03 <https://github.com/TYPO3-Solr/ext-solr/commit/08b846b7c>`_ (thanks to Rafael Kähm)
- [TEMP:T12] Run all test types without failing on first type on GH-Actions `6481b5cb5 on @2023-02-03 <https://github.com/TYPO3-Solr/ext-solr/commit/6481b5cb5>`_ (thanks to Rafael Kähm)
- [TEMP:FIX] Integration tests  for typo3/cms-composer-installers v5 `6adff4a8b on @2023-02-03 <https://github.com/TYPO3-Solr/ext-solr/commit/6adff4a8b>`_ (thanks to Rafael Kähm)
- !!![TASK] Backend modul registration `6f573bff7 on @2023-01-25 <https://github.com/TYPO3-Solr/ext-solr/commit/6f573bff7>`_ (thanks to Lars Tode)
- !!![TASK] Icon registration `f4a7a06e2 on @2023-01-25 <https://github.com/TYPO3-Solr/ext-solr/commit/f4a7a06e2>`_ (thanks to Lars Tode)
- [TASK] Backend service configuration `5e9c3fb11 on @2023-01-30 <https://github.com/TYPO3-Solr/ext-solr/commit/5e9c3fb11>`_ (thanks to Lars Tode)
- [TASK] Backend: Core optimization `bdcbf3c25 on @2023-01-30 <https://github.com/TYPO3-Solr/ext-solr/commit/bdcbf3c25>`_ (thanks to Lars Tode)
- [TASK:T12] Fix TSFE initialization for TYPO3 v12.2+ `8b0c97601 on @2023-02-10 <https://github.com/TYPO3-Solr/ext-solr/commit/8b0c97601>`_ (thanks to Rafael Kähm)
- [TASK:T12] Fix basic troubles in BE modules and make them callable `4c10869e0 on @2023-02-10 <https://github.com/TYPO3-Solr/ext-solr/commit/4c10869e0>`_ (thanks to Rafael Kähm)
- [TASK:T12] Fix Unit\Domain\Search\ApacheSolrDocument\BuilderTest tests `9891c420f on @2023-02-17 <https://github.com/TYPO3-Solr/ext-solr/commit/9891c420f>`_ (thanks to Rafael Kähm)
- [TASK] setup dg/bypass-finals PHPUnit extension to be able to mock finals `c7a22b98d on @2023-02-17 <https://github.com/TYPO3-Solr/ext-solr/commit/c7a22b98d>`_ (thanks to Rafael Kähm)
- [TASK:T12] Fix Tests for ApacheSolrForTypo3\Solr\Controller\Backend\* `2be0e56b4 on @2023-02-17 <https://github.com/TYPO3-Solr/ext-solr/commit/2be0e56b4>`_ (thanks to Rafael Kähm)
- [TASK:T12] Migrate PageModuleSummary to PageContentPreviewRendering `6baf77409 on @2023-02-17 <https://github.com/TYPO3-Solr/ext-solr/commit/6baf77409>`_ (thanks to Rafael Kähm)
- Task: Move pageTsConfig to Configuration/page.tsconfig `f456e0f29 on @2023-02-19 <https://github.com/TYPO3-Solr/ext-solr/commit/f456e0f29>`_ (thanks to Christoph Lehmann)
- [TASK:T12] Fix tests for *\Facets\* namespace `f22028dd5 on @2023-02-18 <https://github.com/TYPO3-Solr/ext-solr/commit/f22028dd5>`_ (thanks to Rafael Kähm)
- [TASK:T12] Fix Unit\IndexQueue\PageIndexerRequestTest tests `2aa944e13 on @2023-02-18 <https://github.com/TYPO3-Solr/ext-solr/commit/2aa944e13>`_ (thanks to Rafael Kähm)
- [TASK:T12] Partially migrated ControllerContext `54fcbcb6e on @2023-02-24 <https://github.com/TYPO3-Solr/ext-solr/commit/54fcbcb6e>`_ (thanks to Rafael Kähm)
- [TASK:T12] Remove usages of sys_language `2c82e4984 on @2023-02-24 <https://github.com/TYPO3-Solr/ext-solr/commit/2c82e4984>`_ (thanks to Rafael Kähm)
- [BUGFIX:T12] Use correct eval for site tca `25a9c6250 on @2023-03-10 <https://github.com/TYPO3-Solr/ext-solr/commit/25a9c6250>`_ (thanks to Georg Ringer)
- [TASK:T12] Migrate content object SOLR_RELATION to TYPO3 12 API `7b5cbcc37 on @2023-03-02 <https://github.com/TYPO3-Solr/ext-solr/commit/7b5cbcc37>`_ (thanks to Rafael Kähm)
- [TASK:T12] Fix Integration\IndexServiceTest `ead957ef3 on @2023-03-02 <https://github.com/TYPO3-Solr/ext-solr/commit/ead957ef3>`_ (thanks to Rafael Kähm)
- [TASK] Fix Ingration tests: ResultSetReconstitutionProcessorTest and ApacheSolrDocumentRepositoryTest `3d3aae9e3 on @2023-03-03 <https://github.com/TYPO3-Solr/ext-solr/commit/3d3aae9e3>`_ (thanks to Rafael Kähm)
- [TASK] Use PhpUnit --testdox output to prevent GH-Actions from freezing `c80272320 on @2023-03-10 <https://github.com/TYPO3-Solr/ext-solr/commit/c80272320>`_ (thanks to Rafael Kähm)
- [BUGFIX] Fix type hinting issue on save scheduler indexing task `886261391 on @2023-03-10 <https://github.com/TYPO3-Solr/ext-solr/commit/886261391>`_ (thanks to Rafael Kähm)
- [TASK:T12] Migrate Page indexing stack to TYPO3 12+ API `a63187347 on @2023-03-10 <https://github.com/TYPO3-Solr/ext-solr/commit/a63187347>`_ (thanks to Rafael Kähm)
- [TASK:T12] Migrate Page indexing stack to TYPO3 12+ API :: Core #98303 `01b2de4f3 on @2023-03-16 <https://github.com/TYPO3-Solr/ext-solr/commit/01b2de4f3>`_ (thanks to Rafael Kähm)
- [TASK:T12] Fix integration tests :: column "cruser_id" does not exist `e76d9af67 on @2023-03-17 <https://github.com/TYPO3-Solr/ext-solr/commit/e76d9af67>`_ (thanks to Rafael Kähm)
- [TASK:T12] Fix integration tests :: SiteHandlingStatusTest `4e1b9a106 on @2023-03-17 <https://github.com/TYPO3-Solr/ext-solr/commit/4e1b9a106>`_ (thanks to Rafael Kähm)
- Revert "[TASK] Use PhpUnit --testdox output to prevent GH-Actions from freezing" `5fd8ec65e on @2023-03-10 <https://github.com/TYPO3-Solr/ext-solr/commit/5fd8ec65e>`_ (thanks to Rafael Kähm)
- [BUGFIX] Add named array keys to items for Index Queue `8c1b081e9 on @2023-03-30 <https://github.com/TYPO3-Solr/ext-solr/commit/8c1b081e9>`_ (thanks to Stefan Froemken)
- [BUGFIX]: Migrate checkboxToggle `6646388d8 on @2023-03-30 <https://github.com/TYPO3-Solr/ext-solr/commit/6646388d8>`_ (thanks to Tim Dreier)
- [BUGFIX] Implement own VariableProvider `93b9334b7 on @2023-03-30 <https://github.com/TYPO3-Solr/ext-solr/commit/93b9334b7>`_ (thanks to Stefan Froemken)
- [TASK] Delete obsolete SolrControllerContext `62cdc523c on @2023-03-30 <https://github.com/TYPO3-Solr/ext-solr/commit/62cdc523c>`_ (thanks to Markus Friedrich)
- [BUGFIX] Set query modifiers as public in Services.yaml `d1fd51d8d on @2023-03-30 <https://github.com/TYPO3-Solr/ext-solr/commit/d1fd51d8d>`_ (thanks to Stefan Froemken)
- [BUGFIX] Replace ControllerContext `eb3bfa9ef on @2023-03-30 <https://github.com/TYPO3-Solr/ext-solr/commit/eb3bfa9ef>`_ (thanks to Stefan Froemken)
- [BUGFIX] Negate condition in Faceting::modifyQuery `a1122baa6 on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/a1122baa6>`_ (thanks to Stefan Froemken)
- [BUGFIX] Remove getControllerContext from UnitTests `35e7b16a3 on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/35e7b16a3>`_ (thanks to Stefan Froemken)
- [BUGFIX] Repair ViewHelper UnitTests `f9f7fdb26 on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/f9f7fdb26>`_ (thanks to Stefan Froemken)
- [BUGFIX] Repair all UnitTests `3effd6e15 on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/3effd6e15>`_ (thanks to Stefan Froemken)
- [BUGFIX] Fix Extbase request enrichment `e9eaa3b91 on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/e9eaa3b91>`_ (thanks to Markus Friedrich)
- !!![TASK] Drop custom translate view helper `1aca300bf on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/1aca300bf>`_ (thanks to Markus Friedrich)
- [TASK] Use static function where possible `087505dce on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/087505dce>`_ (thanks to Stefan Froemken)
- [TASK] Remove class.ext_update.php `8cb3d21f0 on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/8cb3d21f0>`_ (thanks to Stefan Froemken)
- [TASK] Replace list() calls `0cbad95da on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/0cbad95da>`_ (thanks to Stefan Froemken)
- [TASK] Streamline die() call in config files `c3a61223e on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/c3a61223e>`_ (thanks to Stefan Froemken)
- [TASK] Prapare the version stack infos for TYPO3 12.4 LTS `da722f7fa on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/da722f7fa>`_ (thanks to Rafael Kähm)
- [TASK] Migrate Integration tests to fixture extensions `d531cad8c on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/d531cad8c>`_ (thanks to Benni Mack)
- [TASK] Have FrontendController Tests use subrequest logic `a0a6e4459 on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/a0a6e4459>`_ (thanks to Benni Mack)
- [BUGFIX] Fix more integration tests `f964633be on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/f964633be>`_ (thanks to Benni Mack)
- [TASK:T12] disable composer process timeout for integration tests `cd24ad980 on @2023-04-06 <https://github.com/TYPO3-Solr/ext-solr/commit/cd24ad980>`_ (thanks to Rafael Kähm)
- [BUGFIX:T12] Use TYPO3 12+ forder structue on CI context `61712565d on @2023-04-08 <https://github.com/TYPO3-Solr/ext-solr/commit/61712565d>`_ (thanks to Rafael Kähm)
- [BUGFIX:T12] Move TCE-Main hooks registration from ext_tables to ext_localconf `cfce9f18e on @2023-04-06 <https://github.com/TYPO3-Solr/ext-solr/commit/cfce9f18e>`_ (thanks to Rafael Kähm)
- [TASK] Code CleanUp `023cc0fd9 on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/023cc0fd9>`_ (thanks to Stefan Froemken)
- [TASK] Code CleanUp and remove unused methods `3276728ee on @2023-04-02 <https://github.com/TYPO3-Solr/ext-solr/commit/3276728ee>`_ (thanks to Rafael Kähm)
- [TASK:T12] Run tests with TYPO3 12 on PRs against main branch `13f94aac8 on @2023-04-08 <https://github.com/TYPO3-Solr/ext-solr/commit/13f94aac8>`_ (thanks to Rafael Kähm)
- [TASK:T12] Fix current CS issues after rebasing `59df535ab on @2023-04-08 <https://github.com/TYPO3-Solr/ext-solr/commit/59df535ab>`_ (thanks to Rafael Kähm)
- [TASK] Use stable testing framework v7 `961a544fe on @2023-05-02 <https://github.com/TYPO3-Solr/ext-solr/commit/961a544fe>`_ (thanks to Benni Mack)
- [BUGFIX] Fix phpunit checks `d341ebdb2 on @2023-05-02 <https://github.com/TYPO3-Solr/ext-solr/commit/d341ebdb2>`_ (thanks to Benni Mack)
- [BUGFIX] Add 12.4 requirements for integration tests `e054a74b2 on @2023-05-07 <https://github.com/TYPO3-Solr/ext-solr/commit/e054a74b2>`_ (thanks to Benni Mack)
- [BUGFIX] Adapt unit tests `9d834c44b on @2023-05-07 <https://github.com/TYPO3-Solr/ext-solr/commit/9d834c44b>`_ (thanks to Benni Mack)
- [TASK] Skip failed tests due to core issue `5df713b1b on @2023-05-08 <https://github.com/TYPO3-Solr/ext-solr/commit/5df713b1b>`_ (thanks to Benni Mack)
- [TASK] Migrate test fixtures to CSV `6cbc79d40 on @2023-04-25 <https://github.com/TYPO3-Solr/ext-solr/commit/6cbc79d40>`_ (thanks to Benni Mack)
- [BUGFIX] Fix LastSearchesRepositoryTest `86691019c on @2023-05-08 <https://github.com/TYPO3-Solr/ext-solr/commit/86691019c>`_ (thanks to Benni Mack)
- [TASK] Re-enable GarbaCollector Tests `ee6482be2 on @2023-05-08 <https://github.com/TYPO3-Solr/ext-solr/commit/ee6482be2>`_ (thanks to Benni Mack)
- [BUGFIX] Remove unused PHP import `451a197e1 on @2023-05-08 <https://github.com/TYPO3-Solr/ext-solr/commit/451a197e1>`_ (thanks to Benni Mack)
- [BUGFIX] Fix ocular path in GitHub workflow `6a0c2f5a0 on @2023-05-11 <https://github.com/TYPO3-Solr/ext-solr/commit/6a0c2f5a0>`_ (thanks to Benni Mack)
- [TASK] Simplify further Integration tests `e92f8203b on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/e92f8203b>`_ (thanks to Benni Mack)
- [BUGFIX] Fix issues related to PageIndexerTest `da32c19bb on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/da32c19bb>`_ (thanks to Benni Mack)
- [TASK] Use testing framework v7 code `73fe1c2a5 on @2023-05-11 <https://github.com/TYPO3-Solr/ext-solr/commit/73fe1c2a5>`_ (thanks to Benni Mack)
- [TASK] Further migrations for tests `7caafa9ab on @2023-05-12 <https://github.com/TYPO3-Solr/ext-solr/commit/7caafa9ab>`_ (thanks to Benni Mack)
- [BUGFIX] Fix strict checks and migrations for v12 `dafe9cac1 on @2023-05-12 <https://github.com/TYPO3-Solr/ext-solr/commit/dafe9cac1>`_ (thanks to Benni Mack)
- [BUGFIX] Minor additions and CS checks `f4084aa51 on @2023-05-12 <https://github.com/TYPO3-Solr/ext-solr/commit/f4084aa51>`_ (thanks to Benni Mack)
- [TASK] Migrate more fixtures to CSV `b90eee705 on @2023-05-12 <https://github.com/TYPO3-Solr/ext-solr/commit/b90eee705>`_ (thanks to Benni Mack)
- [TASK] Migrate IndexQueue + Garbage Collector Fixtures to CSV `a90a45343 on @2023-05-12 <https://github.com/TYPO3-Solr/ext-solr/commit/a90a45343>`_ (thanks to Benni Mack)
- [TASK] Clean up various test scenarios (#3637) `689ad067f on @2023-05-16 <https://github.com/TYPO3-Solr/ext-solr/commit/689ad067f>`_ (thanks to Benni Mack)
- [DOC] Fix wrong type for boostQuery in the docs and example `131d956d2 on @2023-05-26 <https://github.com/TYPO3-Solr/ext-solr/commit/131d956d2>`_ (thanks to Rafael Kähm)
- [TASK] Integrate phpstan `2ab38d61d on @2023-05-16 <https://github.com/TYPO3-Solr/ext-solr/commit/2ab38d61d>`_ (thanks to Benni Mack)
- [TASK] Migrate more unit tests to clean API and make unit tests work again `6f9c3c218 on @2023-05-16 <https://github.com/TYPO3-Solr/ext-solr/commit/6f9c3c218>`_ (thanks to Benni Mack)
- [TASK] Fix more phpstan issues `9fe24260f on @2023-05-16 <https://github.com/TYPO3-Solr/ext-solr/commit/9fe24260f>`_ (thanks to Benni Mack)
- [TASK] Fix more phpstan issues and remove scrutinizer information `f56aa50ec on @2023-05-16 <https://github.com/TYPO3-Solr/ext-solr/commit/f56aa50ec>`_ (thanks to Benni Mack)
- [TASK] Update all code pieces for phpstan within Tests folder `355203f70 on @2023-05-25 <https://github.com/TYPO3-Solr/ext-solr/commit/355203f70>`_ (thanks to Benni Mack)
- [TASK] Update all code pieces for phpstan within Classes/ folder `950c8b4d2 on @2023-05-26 <https://github.com/TYPO3-Solr/ext-solr/commit/950c8b4d2>`_ (thanks to Rafael Kähm)
- [TASK] remove scritinizer static analyse fixes from sources `1f023bddb on @2023-05-26 <https://github.com/TYPO3-Solr/ext-solr/commit/1f023bddb>`_ (thanks to Rafael Kähm)
- [TASK] Run PHPStan analysis within CI `32310da72 on @2023-05-26 <https://github.com/TYPO3-Solr/ext-solr/commit/32310da72>`_ (thanks to Rafael Kähm)
- [TASK] Minimize footprint of composer dependencies `950b05ea3 on @2023-05-26 <https://github.com/TYPO3-Solr/ext-solr/commit/950b05ea3>`_ (thanks to Rafael Kähm)
- [TASK] Decrease RAMFS size to 1GB since --prefer-source not required anymore `55f2e78d5 on @2023-05-26 <https://github.com/TYPO3-Solr/ext-solr/commit/55f2e78d5>`_ (thanks to Rafael Kähm)
- [TASK] Remove unused tx_solr_cache DB tables (#3638) `15891e151 on @2023-05-30 <https://github.com/TYPO3-Solr/ext-solr/commit/15891e151>`_ (thanks to Benni Mack)
- [!!!][TASK] Remove deprecated functionality `192664daa on @2023-03-31 <https://github.com/TYPO3-Solr/ext-solr/commit/192664daa>`_ (thanks to Benni Mack)
- [TASK] Raise phpstan to level 5 and remove prophecy tests `f0c3d6cae on @2023-05-27 <https://github.com/TYPO3-Solr/ext-solr/commit/f0c3d6cae>`_ (thanks to Benni Mack)
- [TASK] Raise phpstan to level 5 and fix issues within Classes/ `d9e4f9dac on @2023-05-31 <https://github.com/TYPO3-Solr/ext-solr/commit/d9e4f9dac>`_ (thanks to Rafael Kähm)
- Prevent PHP warning in PageBrowserRangeViewHelper `d30ac004f on @2023-06-01 <https://github.com/TYPO3-Solr/ext-solr/commit/d30ac004f>`_ (thanks to Stefan Froemken)
- Use f:translate insteadof s:translate in Index.html `6c58b827d on @2023-06-01 <https://github.com/TYPO3-Solr/ext-solr/commit/6c58b827d>`_ (thanks to Stefan Froemken)
- Remove old txt files for TypoScript `5bd1e105b on @2023-06-01 <https://github.com/TYPO3-Solr/ext-solr/commit/5bd1e105b>`_ (thanks to Stefan Froemken)
- [TASK] Simplify report registration `706d03ab0 on @2023-06-01 <https://github.com/TYPO3-Solr/ext-solr/commit/706d03ab0>`_ (thanks to Markus Friedrich)
- [TASK] Remove obsolete PHP filter_var report `01af1cd17 on @2023-06-01 <https://github.com/TYPO3-Solr/ext-solr/commit/01af1cd17>`_ (thanks to Markus Friedrich)
- [TASK] Improve Solr reports output `ae3aef162 on @2023-06-01 <https://github.com/TYPO3-Solr/ext-solr/commit/ae3aef162>`_ (thanks to Markus Friedrich)
- [TASK] Simplify phpunit invocations `245e0dbca on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/245e0dbca>`_ (thanks to Benni Mack)
- [!!!][FEATURE] Add new Events for Indexing `57a36bac3 on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/57a36bac3>`_ (thanks to Benni Mack)
- [TASK] Remove last fragments of csh usage `22804db29 on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/22804db29>`_ (thanks to Stefan Frömken)
- [TASK] Housekeeping: Remove cache key from ci.yaml `ba4557855 on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/ba4557855>`_ (thanks to Benni Mack)
- [!!!][FEATURE] Add new PSR-14 instead of SubstitutePageIndexer `1e23b41fc on @2023-06-01 <https://github.com/TYPO3-Solr/ext-solr/commit/1e23b41fc>`_ (thanks to Benni Mack)
- [!!!][TASK] Remove UriStrategy logic and move to PSR-14 event `dc6b946bb on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/dc6b946bb>`_ (thanks to Benni Mack)
- [!!!][FEATURE] Migrate IndexQueue hooks to PSR-14 events `17f46ce7e on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/17f46ce7e>`_ (thanks to Benni Mack)
- [TASK:T12] Restore button to requeue document in solr-info -> "Index Documents" `dfc1435b6 on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/dfc1435b6>`_ (thanks to Stefan Frömken)
- [!!!][FEATURE] Migrate VariantIdModifier hook to PSR-14 event `2f421fb50 on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/2f421fb50>`_ (thanks to Benni Mack)
- [!!!][FEATURE] Migrate Index Queue Hook to PSR-14 event `6a7567816 on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/6a7567816>`_ (thanks to Benni Mack)
- [TASK] Reduce usages of Util class `d061e008c on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/d061e008c>`_ (thanks to Benni Mack)
- [!!!][TASK] Rework frontend indexing helpers `1a926ce1a on @2023-06-05 <https://github.com/TYPO3-Solr/ext-solr/commit/1a926ce1a>`_ (thanks to Benni Mack)
- [!!!][FEATURE] Rework Search Component system `e8cd0901e on @2023-06-05 <https://github.com/TYPO3-Solr/ext-solr/commit/e8cd0901e>`_ (thanks to Benni Mack)
- [!!!][TASK] Remove deprecated and unused code `092e2a729 on @2023-06-05 <https://github.com/TYPO3-Solr/ext-solr/commit/092e2a729>`_ (thanks to Benni Mack)
- [TASK] Use Apache Solr 9.2 for EXT:solr 12.0 `1b75d382f on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/1b75d382f>`_ (thanks to Rafael Kähm)
- [TASK] Rename previously created PSR-14 events `4c96b412c on @2023-06-05 <https://github.com/TYPO3-Solr/ext-solr/commit/4c96b412c>`_ (thanks to Benni Mack)
- [TASK] Migrate EXT:solrfluidgrouping into EXT:solr `c55d133c1 on @2023-06-01 <https://github.com/TYPO3-Solr/ext-solr/commit/c55d133c1>`_ (thanks to Stefan Froemken)
- [TASK] Add basic grouping configuration to default TypoScript `a73cb7204 on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/a73cb7204>`_ (thanks to Markus Friedrich)
- [BUGFIX] Fix display of grouped and ungrouped results `73535064c on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/73535064c>`_ (thanks to Markus Friedrich)
- [CLEANUP] Delete obsolete grouped suggest example `4b587694b on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/4b587694b>`_ (thanks to Markus Friedrich)
- [TASK] Simplify configuration access in GroupItemPaginateViewHelper `b1a291570 on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/b1a291570>`_ (thanks to Markus Friedrich)
- [BUGFIX] Adjust EXT:solr version for grouping support `7c3abb332 on @2023-06-05 <https://github.com/TYPO3-Solr/ext-solr/commit/7c3abb332>`_ (thanks to Markus Friedrich)
- [BUGFIX] Fix handling of GET parameter tx_solr[grouping] `aee4e44e4 on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/aee4e44e4>`_ (thanks to Markus Friedrich)
- [TASK] Use PSR3-LoggerTrait for SolrLogManager (#3680) `fb9043c8a on @2023-06-07 <https://github.com/TYPO3-Solr/ext-solr/commit/fb9043c8a>`_ (thanks to Benni Mack)
- [TASK] revert unnecessary changes on Apache Solr 9.2 upgrade `0dd90ffe7 on @2023-06-07 <https://github.com/TYPO3-Solr/ext-solr/commit/0dd90ffe7>`_ (thanks to Rafael Kähm)
- [FEATURE] Introduce TYPO3_SOLR_ENABLED_CORES docker env variable `3d7bb1d35 on @2023-06-07 <https://github.com/TYPO3-Solr/ext-solr/commit/3d7bb1d35>`_ (thanks to Christoph Lehmann)
- !!![TASK] Don't index shortcut pages anymore `18c49ab0d on @2023-06-07 <https://github.com/TYPO3-Solr/ext-solr/commit/18c49ab0d>`_ (thanks to Rafael Kähm)
- [TASK] Make it possible to change solr unix GID:UID on docker image build `a71fe94c1 on @2023-06-08 <https://github.com/TYPO3-Solr/ext-solr/commit/a71fe94c1>`_ (thanks to Rafael Kähm)
- [TASK:T12] Fix unit tests for 2023.06.07 `3ae2902ec on @2023-06-07 <https://github.com/TYPO3-Solr/ext-solr/commit/3ae2902ec>`_ (thanks to Rafael Kähm)
- 12.0.0 BETA-1 Release `8af73fd7d on @2023-06-12 <https://github.com/TYPO3-Solr/ext-solr/commit/8af73fd7d>`_ (thanks to Rafael Kähm)
- [TASK] Add PHP 8.2 to test matrix of TYPO3 11 LTS and remove T12 compatibility `b46c8ccd7 on @2023-05-26 <https://github.com/TYPO3-Solr/ext-solr/commit/b46c8ccd7>`_ (thanks to Rafael Kähm)
- [TASK] Add PHP 8.3 to test matrix `9a72bbc07 on @2023-06-13 <https://github.com/TYPO3-Solr/ext-solr/commit/9a72bbc07>`_ (thanks to Rafael Kähm)
- [TASK:T12] simplify FQDNs in ext_localconf.php `e1268e5da on @2023-06-13 <https://github.com/TYPO3-Solr/ext-solr/commit/e1268e5da>`_ (thanks to Rafael Kähm)
- [TASK:T12] DI for IndexQueueWorkerTaskAdditionalFieldProvider::$siteRepository `06ff4a549 on @2023-06-01 <https://github.com/TYPO3-Solr/ext-solr/commit/06ff4a549>`_ (thanks to Rafael Kähm)
- [BUGFIX] Correct return type in AbstractSolrService `f4d139520 on @2023-06-16 <https://github.com/TYPO3-Solr/ext-solr/commit/f4d139520>`_ (thanks to Oliver Bartsch)
- [BUGFIX] Make BeforeSearchResultIsShownEvent more usable `4cf34a0e0 on @2023-06-26 <https://github.com/TYPO3-Solr/ext-solr/commit/4cf34a0e0>`_ (thanks to Georg Ringer)
- [TASK] Set TYPO3 12.4.2+ as dependency `a0f77359b on @2023-06-23 <https://github.com/TYPO3-Solr/ext-solr/commit/a0f77359b>`_ (thanks to Rafael Kähm)
- [TASK] Make it possible to render the docs in HTML in modern TYPO3 way `8955d5372 on @2023-07-01 <https://github.com/TYPO3-Solr/ext-solr/commit/8955d5372>`_ (thanks to Rafael Kähm)
- [BUGFIX][DOC] Version Matrix for TYPO3 11+12 is not rendered `ede34d56d on @2023-06-30 <https://github.com/TYPO3-Solr/ext-solr/commit/ede34d56d>`_ (thanks to Rafael Kähm)
- [FIX] typo for docs gen. script `9886e743c on @2023-07-03 <https://github.com/TYPO3-Solr/ext-solr/commit/9886e743c>`_ (thanks to Rafael Kähm)
- [TASK] Fix copy&paste error in comment `774662313 on @2023-07-04 <https://github.com/TYPO3-Solr/ext-solr/commit/774662313>`_ (thanks to Georg Ringer)
- [TASK] Improve site and document id determination `9adf0ab17 on @2023-06-28 <https://github.com/TYPO3-Solr/ext-solr/commit/9adf0ab17>`_ (thanks to Markus Friedrich)
- [TASK:T12] EXT:solr* addons adaptions `c5c3a5e4a on @2023-06-30 <https://github.com/TYPO3-Solr/ext-solr/commit/c5c3a5e4a>`_ (thanks to Rafael Kähm)
- [TASK] Fix Github deprecations and update actions/* versions `0180e1655 on @2023-07-13 <https://github.com/TYPO3-Solr/ext-solr/commit/0180e1655>`_ (thanks to Rafael Kähm)
- [TASK] Allow to run tests:phpstan within solr-ddev-site environment `7306a6b8e on @2023-07-13 <https://github.com/TYPO3-Solr/ext-solr/commit/7306a6b8e>`_ (thanks to Rafael Kähm)
- [TASK] move composer lint:xlf to tests:lint-xml `6820c6ef8 on @2023-07-20 <https://github.com/TYPO3-Solr/ext-solr/commit/6820c6ef8>`_ (thanks to Rafael Kähm)
- BUGFIX: Add missing label key to prevent php warning `35de99d16 on @2023-07-11 <https://github.com/TYPO3-Solr/ext-solr/commit/35de99d16>`_ (thanks to Sascha Nowak)
- [BUGFIX] Cast pageType and suggestPageType to int in SearchFormViewHelper `110fff260 on @2023-07-14 <https://github.com/TYPO3-Solr/ext-solr/commit/110fff260>`_ (thanks to Till Hörner)
- [FEATURE] enable time-limited pages to be indexed automatically `4dd5d415e on @2023-07-21 <https://github.com/TYPO3-Solr/ext-solr/commit/4dd5d415e>`_ (thanks to Dmitry Dulepov)
- [TASK] Use correct spellings for proper nouns `64b85e98d on @2023-08-10 <https://github.com/TYPO3-Solr/ext-solr/commit/64b85e98d>`_ (thanks to Markus Friedrich)
- !!![TASK] Upgrade to Apache Solr 9.3.0 `211218214 on @2023-08-07 <https://github.com/TYPO3-Solr/ext-solr/commit/211218214>`_ (thanks to Rafael Kähm)
- [TASK] Sync with EXT:solr addons :: Fix Type-Hinting issues `aee51e499 on @2023-08-11 <https://github.com/TYPO3-Solr/ext-solr/commit/aee51e499>`_ (thanks to Rafael Kähm)
- [TASK] Sync with EXT:solr addons :: Fix Type-Hinting for AfterIndexQueueItemHasBeenMarkedForReindexingEvent `d96e4ebf0 on @2023-08-14 <https://github.com/TYPO3-Solr/ext-solr/commit/d96e4ebf0>`_ (thanks to Rafael Kähm)
- [TASK] Sync with EXT:solr addons :: fix old linter issues `b15df830e on @2023-08-14 <https://github.com/TYPO3-Solr/ext-solr/commit/b15df830e>`_ (thanks to Rafael Kähm)
- [BUGFIX] prevent Exception when create Event Queue Worker Task `ee4b19cda on @2023-08-25 <https://github.com/TYPO3-Solr/ext-solr/commit/ee4b19cda>`_ (thanks to Achim Fritz)
- [DOC] Update Version Matrix `c1a1d98ae on @2023-09-07 <https://github.com/TYPO3-Solr/ext-solr/commit/c1a1d98ae>`_ (thanks to Jennifer Geiß)
- [TASK] Use newest dkd logo in README `3c6fce91c on @2023-08-15 <https://github.com/TYPO3-Solr/ext-solr/commit/3c6fce91c>`_ (thanks to Rafael Kähm)
- [BUGFIX] Fix EXT:solr route enhancer `b7224634b on @2023-08-02 <https://github.com/TYPO3-Solr/ext-solr/commit/b7224634b>`_ (thanks to Markus Friedrich)
- [BUG] Fix detection of "draft records" in workspaces `0c09c65aa on @2023-05-22 <https://github.com/TYPO3-Solr/ext-solr/commit/0c09c65aa>`_ (thanks to Ernesto Baschny)
- !!![BUGFIX] Queue check considers indexing configuration `5b865ea12 on @2022-09-08 <https://github.com/TYPO3-Solr/ext-solr/commit/5b865ea12>`_ (thanks to Markus Friedrich)
- !!![TASK] Introduce queue and queue item interfaces `6867b7333 on @2022-08-31 <https://github.com/TYPO3-Solr/ext-solr/commit/6867b7333>`_ (thanks to Markus Friedrich)
- [TASK] Consider queue initialization status `c2e84f944 on @2022-09-02 <https://github.com/TYPO3-Solr/ext-solr/commit/c2e84f944>`_ (thanks to Markus Friedrich)
- !!![TASK] Introduce specific EXT:solr exceptions `1ed1c4ae2 on @2022-09-08 <https://github.com/TYPO3-Solr/ext-solr/commit/1ed1c4ae2>`_ (thanks to Markus Friedrich)
- [TASK] Fix PHP-CS for `single_line_empty_body` rule `22f79b113 on @2023-09-18 <https://github.com/TYPO3-Solr/ext-solr/commit/22f79b113>`_ (thanks to Rafael Kähm)
- [BUGFIX] Do not index translations on default language in languages free mode `a4013bdb2 on @2023-09-18 <https://github.com/TYPO3-Solr/ext-solr/commit/a4013bdb2>`_ (thanks to André Buchmann)
- [BUGFIX] Retry Uri Building after exception `ba1de1cd0 on @2023-03-29 <https://github.com/TYPO3-Solr/ext-solr/commit/ba1de1cd0>`_ (thanks to Mario Lubenka)
- [BUGFIX] Delete index documents without available site `8f607900d on @2023-08-29 <https://github.com/TYPO3-Solr/ext-solr/commit/8f607900d>`_ (thanks to Elias Häußler)
- [TASK] Ensure recursive page update on page movement `59374212b on @2023-08-30 <https://github.com/TYPO3-Solr/ext-solr/commit/59374212b>`_ (thanks to Markus Friedrich)
- [FEATURE] Add index queue indices `df3124182 on @2023-09-20 <https://github.com/TYPO3-Solr/ext-solr/commit/df3124182>`_ (thanks to Markus Friedrich)
- [TASK] Update to solarium/solarium 6.3.2 `7674000fd on @2023-09-21 <https://github.com/TYPO3-Solr/ext-solr/commit/7674000fd>`_ (thanks to Markus Friedrich)
- [TASK] Update non-composer package dependencies `f49dbf324 on @2023-09-21 <https://github.com/TYPO3-Solr/ext-solr/commit/f49dbf324>`_ (thanks to Markus Friedrich)
- [TASK] Migrate top.fsMod `3da50d4ac on @2023-09-21 <https://github.com/TYPO3-Solr/ext-solr/commit/3da50d4ac>`_ (thanks to Markus Friedrich)
- [TASK] Replace RequireJS `fcf82f670 on @2023-09-21 <https://github.com/TYPO3-Solr/ext-solr/commit/fcf82f670>`_ (thanks to Markus Friedrich)
- [TASK] Require TYPO3 12.4.3 to get typo3fluid/fluid >= 2.9.2 `a528113bf on @2023-09-21 <https://github.com/TYPO3-Solr/ext-solr/commit/a528113bf>`_ (thanks to Markus Friedrich)
- [TASK] Always delegate simulated TSFE via PSR-14 events instead of Site/SiteLanguage `afb986e40 on @2023-09-22 <https://github.com/TYPO3-Solr/ext-solr/commit/afb986e40>`_ (thanks to Rafael Kähm)
- [BUGFIX] Fix result highlighting fragment size `f528685c2 on @2023-09-25 <https://github.com/TYPO3-Solr/ext-solr/commit/f528685c2>`_ (thanks to Markus Friedrich)
- !!![CLEANUP] Remove obsolete TYPO3 registry usages `81b8f2b4a on @2023-09-25 <https://github.com/TYPO3-Solr/ext-solr/commit/81b8f2b4a>`_ (thanks to Markus Friedrich)
- [FEATURE] Allow setting documents `137742348 on @2023-09-28 <https://github.com/TYPO3-Solr/ext-solr/commit/137742348>`_ (thanks to Georg Ringer)
- [BUGFIX] Return value getPageItemChangedTime() must be of the type int `4edc7cbad on @2023-10-06 <https://github.com/TYPO3-Solr/ext-solr/commit/4edc7cbad>`_ (thanks to Rafael Kähm)
- [!!!][TASK] Remove deprecated Node class `5043cdd83 on @2023-06-02 <https://github.com/TYPO3-Solr/ext-solr/commit/5043cdd83>`_ (thanks to Markus Friedrich)
- [TASK] Clean-Up the docs by deleting not available stuff `21e06a7ed on @2023-10-06 <https://github.com/TYPO3-Solr/ext-solr/commit/21e06a7ed>`_ (thanks to Rafael Kähm)
- [BUGFIX] Fix facet route enhancer `3c402b44a on @2023-10-09 <https://github.com/TYPO3-Solr/ext-solr/commit/3c402b44a>`_ (thanks to Markus Friedrich)
- !!![TASK] Configuration option enableRouteEnhancer `529b21ae1 on @2023-10-09 <https://github.com/TYPO3-Solr/ext-solr/commit/529b21ae1>`_ (thanks to Markus Friedrich)
- [TASK] Remove duplicate withHeader() `f35f6526d on @2023-07-26 <https://github.com/TYPO3-Solr/ext-solr/commit/f35f6526d>`_ (thanks to Christoph Lehmann)
- [BUGFIX] Fix indexing of access protected pages `a6168bcc6 on @2023-10-04 <https://github.com/TYPO3-Solr/ext-solr/commit/a6168bcc6>`_ (thanks to Markus Friedrich)
- !!![TASK] Clean and optimize frontend helper: PageIndexer `343147ffc on @2023-10-06 <https://github.com/TYPO3-Solr/ext-solr/commit/343147ffc>`_ (thanks to Markus Friedrich)
- [BUGFIX] Do not list cores twice in Index Inspector `c20f47349 on @2023-04-24 <https://github.com/TYPO3-Solr/ext-solr/commit/c20f47349>`_ (thanks to Christoph Lehmann)
- [BUGFIX] Less strict return types on resolving values `e2b725cf0 on @2023-08-16 <https://github.com/TYPO3-Solr/ext-solr/commit/e2b725cf0>`_ (thanks to Silvia Bigler)
- [BUGFIX] Fixes multiple sortings `358c49fa3 on @2023-05-10 <https://github.com/TYPO3-Solr/ext-solr/commit/358c49fa3>`_ (thanks to Bastien Lutz)
- [BUGFIX] Fix missing frontend.typoscript request attribute while indexing `46a22d00e on @2023-08-16 <https://github.com/TYPO3-Solr/ext-solr/commit/46a22d00e>`_ (thanks to Till Hörner)
- [BUGFIX] Force score to float `158220581 on @2023-06-15 <https://github.com/TYPO3-Solr/ext-solr/commit/158220581>`_ (thanks to Georg Ringer)
- [BUGFIX] Fix possible notice `4de4b725d on @2023-06-27 <https://github.com/TYPO3-Solr/ext-solr/commit/4de4b725d>`_ (thanks to Georg Ringer)
- [CS] Fix PHP CS 2023.10.12 `f00e5cbe5 on @2023-10-12 <https://github.com/TYPO3-Solr/ext-solr/commit/f00e5cbe5>`_ (thanks to Rafael Kähm)
- [DOC] Add FAQ how to generate URLs to restricted pages `42e938ba4 on @2023-10-12 <https://github.com/TYPO3-Solr/ext-solr/commit/42e938ba4>`_ (thanks to Sascha Schieferdecker)
- [BUGFIX] Prevent indexing error on missing 'foreignLabelField' `ca39ec607 on @2023-10-12 <https://github.com/TYPO3-Solr/ext-solr/commit/ca39ec607>`_ (thanks to Rafael Kähm)
- [DOC] Solr claims to be not configured in backend context, although I did it well. What can be the reason? (#3708) `6c6952beb on @2023-10-12 <https://github.com/TYPO3-Solr/ext-solr/commit/6c6952beb>`_ (thanks to haraldwitt)
- !!![FEATURE] Allow using complex filter values in FlexForm `61d1a92ba on @2023-10-12 <https://github.com/TYPO3-Solr/ext-solr/commit/61d1a92ba>`_ (thanks to Eric Chavaillaz)
- [TEST] Handle float values in options facet parser `cb5cdb7d7 on @2023-10-12 <https://github.com/TYPO3-Solr/ext-solr/commit/cb5cdb7d7>`_ (thanks to Rafael Kähm)
- [BUGFIX] Handle float values in options facet parser `b9299f531 on @2023-07-11 <https://github.com/TYPO3-Solr/ext-solr/commit/b9299f531>`_ (thanks to Sascha Nowak)
- [FIX] Inspection in AbstractSolrService `c06f14a87 on @2023-10-13 <https://github.com/TYPO3-Solr/ext-solr/commit/c06f14a87>`_ (thanks to Rafael Kähm)
- [BUGFIX] Fix failed indexing logging `13d586736 on @2023-10-11 <https://github.com/TYPO3-Solr/ext-solr/commit/13d586736>`_ (thanks to Markus Friedrich)
- [BUGFIX] Fix root page handling in SolrRoutingMiddleware `829a5339c on @2023-10-11 <https://github.com/TYPO3-Solr/ext-solr/commit/829a5339c>`_ (thanks to Markus Friedrich)
- [TASK] Add test for Solr route enhancer `9c3dbc369 on @2023-10-11 <https://github.com/TYPO3-Solr/ext-solr/commit/9c3dbc369>`_ (thanks to Markus Friedrich)
- [TASK] Complete extension settings documentation `0d2c64b43 on @2023-10-13 <https://github.com/TYPO3-Solr/ext-solr/commit/0d2c64b43>`_ (thanks to Markus Friedrich)
- [TASK] Improve extension configuration `974951239 on @2023-10-13 <https://github.com/TYPO3-Solr/ext-solr/commit/974951239>`_ (thanks to Markus Friedrich)
- [BUGFIX] handle localizations with un-available tsfe more gracefully `bbdda0cf2 on @2023-07-27 <https://github.com/TYPO3-Solr/ext-solr/commit/bbdda0cf2>`_ (thanks to 3m5. Adam Koppe)
- [TEST] TSFE can be initialized for pages with fe_group="-2" `9ee023535 on @2022-09-23 <https://github.com/TYPO3-Solr/ext-solr/commit/9ee023535>`_ (thanks to Rafael Kähm)
- [BUGFIX] Fix indexing of pages with fe_group=-2 "show at any login" `6118788f0 on @2023-10-13 <https://github.com/TYPO3-Solr/ext-solr/commit/6118788f0>`_ (thanks to Rafael Kähm)
- [BUGFIX] Grouping fails on non-string filed types `3fc39a1d0 on @2023-10-14 <https://github.com/TYPO3-Solr/ext-solr/commit/3fc39a1d0>`_ (thanks to Rafael Kähm)
- [TASK] Reenable skipped test of SearchControllerTest `e9a0d9c4a on @2023-10-16 <https://github.com/TYPO3-Solr/ext-solr/commit/e9a0d9c4a>`_ (thanks to Rafael Kähm)
- `Release 12.0.0 <https://github.com/TYPO3-Solr/ext-solr/releases/tag/12.0.0>`_ (thanks to all `contributors <https://github.com/TYPO3-Solr/ext-solr/graphs/contributors>`_ and `our EB Partners <https://www.typo3-solr.com/sponsors/our-sponsors/>`_)

Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)


- Achim Fritz
- Albrecht Köhnlein
- Alexander Nitsche
- Andreas Kießling
- André Buchmann
- Bastien Lutz
- Benni Mack
- Benoit Chenu
- Christoph Lehmann
- Dmitry Dulepov
- Elias Häußler
- Eric Chavaillaz
- Ernesto Baschny
- Fabio Norbutat
- Florian Rival
- Georg Ringer
- Harald Witt
- Henrik Elsner
- Ingo Fabbri
- Jennifer Geiß
- Lars Tode
- Lukas Niestroj
- Mario Lubenka
- Markus Friedrich
- `@n3amil / Cypelt <https://github.com/n3amil>`_
- Oliver Bartsch
- Rafael Kähm
- René Maas
- Sascha Nowak
- Sascha Schieferdecker
- Sebastian Schreiber
- Silvia Bigler
- Stefan Frömken
- Steve Lenz
- Stämpfli Kommunikation
- Sven Teuber
- Till Hörner
- Tim Dreier
- Tobias Hövelborn
- Tobias Schmidt
- Torben Hansen
- `@twojtylak <https://github.com/twojtylak>`_

Also a big thank you to our partners who have already concluded one of our new development participation packages such
as Apache Solr EB for TYPO3 12 LTS (Feature):


- .hausformat GmbH
- b13 GmbH
- clickstorm GmbH
- Connecta AG
- cron IT GmbH
- CS2 AG
- digit.ly GmbH
- FTI Touristik GmbH
- Hochschule Furtwangen
- in2code GmbH
- internezzo ag
- jweiland.net
- Kassenärztliche Vereinigung Rheinland-Pfalz
- KONVERTO AG
- Leuchtfeuer Digital Marketing GmbH
- Lingner Consulting New Media GmbH
- LOUIS INTERNET GmbH
- mehrwert intermediale kommunikation GmbH
- plan2net GmbH
- queo GmbH
- Québec.ca gouv.qc.ca
- Snowflake Productions GmbH
- Stadtverwaltung Villingen-Schwenningen
- Stämpfli AG
- unternehmen online GmbH & Co. KG
- VisionConnect GmbH
- WIND INTERNET


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
