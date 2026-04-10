.. _releases-14-0:

=============
Releases 14.0
=============

Release 14.0.0
==============

This is a new major release for TYPO3 14 LTS.

New in this release
-------------------

.. note::
   This section will be extended as features are finalized.

TYPO3 14 LTS Compatibility
~~~~~~~~~~~~~~~~~~~~~~~~~~

EXT:solr has been fully adapted for TYPO3 14 LTS, including Fluid v5 ViewHelper
compatibility, TCA changes, deprecation removals, and testing framework updates.

XLIFF 2.0 Migration
~~~~~~~~~~~~~~~~~~~

All language files have been migrated from XLIFF 1.2 to XLIFF 2.0 format.

Parallel Solr Worker Cores for Integration Tests
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Integration tests now use parallel Solr worker cores via paratest, significantly
improving test execution speed.

Event Listener Migration to PHP Attributes
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Event listeners have been refactored to use the ``#[AsEventListener]`` PHP
attribute instead of ``Services.yaml`` tag registration, following TYPO3 14
best practices.

Unified Sub-Request Indexing Pipeline
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The page indexing system has been completely rewritten. The legacy HTTP-based
``PageIndexer`` (which made real HTTP round-trips via ``X-Tx-Solr-Iq`` headers)
has been replaced by a unified in-process sub-request pipeline using TYPO3's
``Application::handle()``.

Key changes:

*  ``IndexingService`` orchestrates all indexing via ``Application::handle()``
   sub-requests — no more HTTP round-trips
*  ``SolrIndexingMiddleware`` handles page rendering, document creation, and
   Solr submission within the standard TYPO3 middleware stack
*  ``UserGroupDetectionMiddleware`` + ``UserGroupDetector`` detect frontend
   user groups during page rendering without Singleton state or TCA manipulation
*  ``CliEnvironment`` and ``forcedWebRoot`` scheduler option removed — sub-requests
   use ``chdir(Environment::getPublicPath())`` to ensure correct working directory
*  12.7% faster indexing (493.9s → 431.3s for 59 pages) with ~3,200 lines removed

See `#4559 <https://github.com/TYPO3-Solr/ext-solr/pull/4559>`_ and
`#4598 <https://github.com/TYPO3-Solr/ext-solr/issues/4598>`_ for details.

Bugfix: No ``c:0`` Variant on fe_group-restricted Pages
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Two interrelated bugs in the new sub-request indexing pipeline caused
access-protected pages to be indexed with incorrect Solr documents.

**Bug 1 — Spurious ``c:0`` variant from global template content:**
During the ``findUserGroups`` sub-request, TYPO3 renders the full page
including global template content (footer, navigation) from other pages.
Content elements without ``fe_group`` restriction in these template areas
caused ``UserGroupDetector`` to collect group ``0``, which then triggered
a ``c:0`` Solr variant even for pages where all actual page content is
access-restricted. With ``fe_group=1`` on the page, the ``c:0`` variant
was never meaningfully accessible (its access rootline already required
group 1), but it polluted the index with empty or incorrect documents.

Fix: When ``pageUserGroup > 0``, group ``0`` is removed from the detected
access groups. The page's own group is added as a fallback so that users
holding only the page group can still find the page in search results.

**Bug 2 — Protected content leaking into the ``c:0`` variant:**
When indexing the ``c:0`` variant (anonymous rendering), ``FrontendGroupsModifier``
unconditionally added ``pageUserGroup`` to the faked frontend groups. This
granted access to ``fe_group``-restricted content elements during the
anonymous rendering sub-request, causing protected content to appear in
the public Solr document — a potential content disclosure issue.

Fix: ``pageUserGroup`` is now only added to the faked groups when
``userGroup > 0`` (i.e. not during the anonymous ``c:0`` rendering).

As a result, pages with ``fe_group`` restriction no longer produce a
``c:0`` Solr document. Instead, the page's own group (e.g. ``c:1``) is
used as the base content access variant.


Breaking Changes
----------------

Unified Site Hash Strategy
~~~~~~~~~~~~~~~~~~~~~~~~~~

Introduced in solr v13.1, and now implemented as default, the site hash
strategy is now based on the site identifier and not on the domain anymore,
making the site hash calculation more robust across sites with multiple domains.

The extension configuration setting: ``siteHashStrategy`` has been removed
without substitution.

The PSR-14 event :php:`AfterDomainHasBeenDeterminedForSiteEvent` has been
removed, as it has been superseded by
:php:`AfterSiteHashHasBeenDeterminedForSiteEvent`.

If you upgrade from < 13.1, it is recommended to re-index all solr cores
completely.


!!! QueueInitializationServiceAwareInterface and related Queue methods removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The interface
:php:`ApacheSolrForTypo3\Solr\IndexQueue\QueueInitializationServiceAwareInterface`
and its implementation in :php:`ApacheSolrForTypo3\Solr\IndexQueue\Queue` have been
removed entirely. The following public API is gone:

*   :php:`Queue::setQueueInitializationService(QueueInitializationService $service): void`
*   :php:`Queue::getQueueInitializationService(): QueueInitializationService`
*   :php:`Queue::getInitializationService(): QueueInitializationService` (was already deprecated since v12)

The :php:`QueueInitializationService` itself is not affected and continues to exist.

Background
""""""""""

The interface was introduced as a workaround for a circular dependency: the
:php:`QueueInitializationService` created :php:`Queue` instances and then injected itself
back via :php:`setQueueInitializationService()`. In practice, the injected service was
never used by :php:`Queue` internally, and :php:`getQueueInitializationService()` was
only called in tests – never in production code. The pattern was obsolete.


!!! DataUpdateHandler::removeFromIndexAndQueueWhenItemInQueue() removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The deprecated method :php:`DataUpdateHandler::removeFromIndexAndQueueWhenItemInQueue(string $recordTable, int $recordUid): void`
has been removed. Use :php:`DataUpdateHandler::removeFromIndexAndQueue()` directly instead.

Impact
""""""

**Code overriding** :php:`removeFromIndexAndQueueWhenItemInQueue()` or calling it from a subclass

Replace every call to :php:`removeFromIndexAndQueueWhenItemInQueue()` with a direct call to :php:`removeFromIndexAndQueue()`.

The queue-containment check that was part of the old method is not needed:
:php:`removeFromIndexAndQueue()` / :php:`GarbageHandler::collectGarbage()` handle that case gracefully.


!!! PageIndexer::isPageIndexable() removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The deprecated method :php:`PageIndexer::isPageIndexable(Item $item): bool` has been removed.
Use :php:`PageIndexer::isPageEnabled(array $record): bool` instead.

Impact
""""""

**Code overriding** :php:`isPageIndexable()` in a custom :php:`PageIndexer` subclass

Rename method :php:`isPageIndexable` to :php:`isPageEnabled()` and adjust the signature to accept an array
(the page record) instead of an :php:`Item` object:


!!! RecordUpdatedEvent no longer covers record insertions – use RecordInsertedEvent
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

A dedicated :php:`RecordInsertedEvent` has been introduced for record creations.
The :php:`RecordUpdatedEvent` now only fires for updates; the deprecated
:php:`$isNewRecord` property, its constructor parameter, and the :php:`isNewRecord()`
method have been removed from :php:`RecordUpdatedEvent`.

Previously :php:`RecordUpdatedEvent` was dispatched for both new records and updates,
with :php:`isNewRecord()` acting as a flag to distinguish the two cases.
In v14 these are two distinct events.

Impact
""""""

**Listeners checking** :php:`$event->isNewRecord()`

Register your listener for :php:`RecordInsertedEvent` to handle creations, and for
:php:`RecordUpdatedEvent` to handle updates. Remove any :php:`isNewRecord()` checks:

..  code-block:: yaml

    # Before – one listener covering both cases
    tags:
      - name: event.listener
        event: ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent

    # After – separate registrations
    tags:
      - name: event.listener
        event: ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordInsertedEvent
      - name: event.listener
        event: ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent

**Code instantiating** :php:`new RecordUpdatedEvent(..., isNewRecord: true)`

Replace with :php:`new RecordInsertedEvent($uid, $table, $fields)`.


!!! Item properties are now non-nullable with strict validation
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:php:`Item` constructor properties (``item_uid``, ``indexing_configuration``, ``changed``)
are now non-nullable and validated strictly. CSV fixtures for integration tests
must include all required columns.


!!! Legacy PageIndexer system removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The HTTP-based page indexing system has been completely removed and replaced by
the unified sub-request pipeline. The following classes no longer exist:

*   :php:`IndexQueue\PageIndexer` — replaced by :php:`IndexQueue\IndexingService`
*   :php:`IndexQueue\PageIndexerRequest` — replaced by :php:`IndexQueue\IndexingInstructions`
*   :php:`IndexQueue\PageIndexerResponse` — replaced by ``JsonResponse``
*   :php:`IndexQueue\PageIndexerRequestHandler`
*   :php:`IndexQueue\PageIndexerDataUrlModifier` (interface)
*   :php:`IndexQueue\FrontendHelper\Manager`
*   :php:`IndexQueue\FrontendHelper\FrontendHelper` (interface)
*   :php:`IndexQueue\FrontendHelper\PageIndexer` (event listener)
*   :php:`Middleware\PageIndexerInitialization`
*   :php:`System\Environment\CliEnvironment`
*   :php:`System\Environment\WebRootAllReadyDefinedException`

The ``UserGroupDetector`` and ``AuthorizationService`` have been moved from
:php:`IndexQueue\FrontendHelper` to the :php:`Middleware` namespace.

The ``forcedWebRoot`` scheduler task option has been removed from
``IndexQueueWorkerTask`` and ``IndexQueueWorkerTaskAdditionalFieldProvider``.

Impact
""""""

**Custom PageIndexer subclasses** must be rewritten to use the new pipeline.
Register event listeners for :php:`AfterPageDocumentIsCreatedForIndexingEvent`
or :php:`BeforeDocumentIsProcessedForIndexingEvent` instead.

**Code referencing** :php:`PageIndexerRequest::SOLR_INDEX_HEADER` (``X-Tx-Solr-Iq``)
should check the ``solr.indexingInstructions`` request attribute instead.

**Code using** :php:`CliEnvironment` for web root initialization should remove
those calls — the sub-request pipeline handles CWD automatically.


!!! Upgrade to Apache Solr 10.0.0
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Latest Apache Solr Release 10.0.0 required, EXT:solr 14 won't support older Apache Solr versions. Along with the switch to Apache Solr 10, Apache Lucene 10 is being used.
A full reindexing is recommended. Please refer to the Apache Solr documentation to find out what major changes Solr 10 brings.

Solr 10 bundles Jetty 12, which strictly rejects ambiguous URI path encoding (HTTP 400). A previous workaround using double rawurlencode() for the managed synonyms and stopwords
API has been removed, as it is incompatible with Jetty 12. Synonym base words and stop words containing non-ASCII characters (e.g. umlauts) are now handled correctly.

In Solr 10 the LocalTikaExtractionBackend (deprecated since Solr 9.10, SOLR-17961) was also removed. The tikaserver backend is now the only supported extraction backend. The `/update/extract`
request handler has been removed from solrconfig.xml accordingly. Users relying on Solr Cell must use EXT:tika v14+ and provide a Tika Server or Tika app

..  warning::
    Synonym base words containing "%" or "/" remain unsupported. "%" is rejected by
    Jetty 12 as potentially ambiguous, and "/" is interpreted as a URI path separator by Solr.
    See: https://issues.apache.org/jira/browse/SOLR-6853

..  warning::
    Users relying on Solr Cell must use EXT:tika v14+ and provide a Tika Server or Tika app and every usage of `SolrWriteService->extractByQuery()` must be refactored to use EXT:tika.


!!! Deprecated dynamic Solr fields dropped
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Since EXT:solr 9 and Apache Solr 7 dynamic fields based on trie fields are marked as deprecated, these fields are now removed:

*   *_tIntS (-> *_intS)
*   *_tInt (-> _intM)
*   *_tLong (-> _longS)
*   *_tLong (-> _longM)
*   *_tFloat (-> _floatS)
*   *_tFloat (-> _floatM)
*   *_tDouble (-> _doubleS)
*   *_tDouble (-> _doubleS)
*   *_tDouble4 (-> _double4S)
*   *_tDouble4 (-> _double4M)
*   *_tDate (-> _dateS)
*   *_tDate (-> _dateM)


All Changes
-----------

*   [BUGFIX] Prevent c:0 variant and content leakage on fe_group-restricted pages by @dkd-kaehm in `#4559 <https://github.com/TYPO3-Solr/ext-solr/pull/4559>`_
*   [!!!][TASK] Remove legacy PageIndexer system and migrate to IndexingInstructions by @dkd-kaehm in `#4559 <https://github.com/TYPO3-Solr/ext-solr/pull/4559>`_
*   [TASK] Set CWD to public path during sub-requests and remove CliEnvironment by @dkd-kaehm in `#4559 <https://github.com/TYPO3-Solr/ext-solr/pull/4559>`_
*   [!!!][TASK] Refactor indexing stack to unified TYPO3 core sub-requests by @dkd-kaehm in `#4559 <https://github.com/TYPO3-Solr/ext-solr/pull/4559>`_
*   [TASK] Upgrade to typo3/testing-framework 9.5.0 by @dkd-kaehm in `#4604 <https://github.com/TYPO3-Solr/ext-solr/pull/4604>`_
*   [TASK] Fix IconFactory::mapRecordTypeToIconIdentifier() call for TYPO3 14 by @dkd-kaehm in `#4604 <https://github.com/TYPO3-Solr/ext-solr/pull/4604>`_
*   [TASK] Upgrade GitHub Actions to latest versions by @dkd-kaehm in `#4601 <https://github.com/TYPO3-Solr/ext-solr/pull/4601>`_
*   [TASK] Implement deferred Solr cleanup + fix worker core isolation by @dkd-kaehm in `#4594 <https://github.com/TYPO3-Solr/ext-solr/pull/4594>`_
*   [TASK] Run integration tests without processIsolation by @bmack in `#4594 <https://github.com/TYPO3-Solr/ext-solr/pull/4594>`_
*   [TASK] Implement parallel Solr worker cores for paratest integration tests by @dkd-kaehm in `#4594 <https://github.com/TYPO3-Solr/ext-solr/pull/4594>`_
*   [TASK] Convert AbstractUriViewHelper to instance properties by @dkd-kaehm in `#4594 <https://github.com/TYPO3-Solr/ext-solr/pull/4594>`_
*   [TASK] Refactor event listeners with AsEventListener attribute by @sfroemkenjw in `#4588 <https://github.com/TYPO3-Solr/ext-solr/pull/4588>`_
*   [BUGFIX] GeneralUtility::trimExplode(): Argument #2 ($string) must be of type string, int given by @kitzberger in `#4511 <https://github.com/TYPO3-Solr/ext-solr/pull/4511>`_
*   [BUGFIX] Cast result offset to integer by @Nowak in `#4529 <https://github.com/TYPO3-Solr/ext-solr/pull/4529>`_
*   [TASK] Refactor and optimize Classification handling by @sfroemkenjw in `#4583 <https://github.com/TYPO3-Solr/ext-solr/pull/4583>`_
*   [TASK] Migrate xlf files of TYPO3 modules to XLIFF format 2.0 by @sfroemkenjw in `#4575 <https://github.com/TYPO3-Solr/ext-solr/pull/4575>`_
*   [TASK] Refactor SettingsPreviewOnPlugins to EventListener by @sfroemkenjw in `#4576 <https://github.com/TYPO3-Solr/ext-solr/pull/4576>`_
*   [TASK] Refactor static function usage in ViewHelpers by @sfroemkenjw in `#4582 <https://github.com/TYPO3-Solr/ext-solr/pull/4582>`_
*   [TASK] Refactor facet ViewHelpers to use shared trait by @sfroemkenjw in `#4580 <https://github.com/TYPO3-Solr/ext-solr/pull/4580>`_
*   [TASK] Fix namespace typo in SearchFormViewHelperTest by @sfroemkenjw in `#4581 <https://github.com/TYPO3-Solr/ext-solr/pull/4581>`_
*   [TASK] Refactor SearchFormViewHelper by @sfroemkenjw in `#4563 <https://github.com/TYPO3-Solr/ext-solr/pull/4563>`_
*   [TASK] Refactor IsStringViewHelperTest to IntegrationTestBase by @sfroemkenjw in `#4578 <https://github.com/TYPO3-Solr/ext-solr/pull/4578>`_
*   [TASK] Simplify test setup in SetUpFacetItemViewHelper by @sfroemkenjw in `#4577 <https://github.com/TYPO3-Solr/ext-solr/pull/4577>`_
*   [TASK] Speed up tests by sending autoCommit for updates by @bmack in `#4565 <https://github.com/TYPO3-Solr/ext-solr/pull/4565>`_
*   [TASK] Refactor UnitTests for Rootline and RootlineElement by @sfroemkenjw in `#4574 <https://github.com/TYPO3-Solr/ext-solr/pull/4574>`_
*   [!!!][TASK] Introduce RecordInsertedEvent, drop isNewRecord from RecordUpdatedEvent by @dkd-friedrich in `#4560 <https://github.com/TYPO3-Solr/ext-solr/pull/4560>`_
*   [!!!][TASK] Remove deprecated DataUpdateHandler::removeFromIndexAndQueueWhenItemInQueue() by @dkd-friedrich in `#4560 <https://github.com/TYPO3-Solr/ext-solr/pull/4560>`_
*   [!!!][TASK] Remove deprecated PageIndexer::isPageIndexable() for v14 by @dkd-friedrich in `#4560 <https://github.com/TYPO3-Solr/ext-solr/pull/4560>`_
*   [!!!][TASK] Remove QueueInitializationServiceAwareInterface and related Queue API by @dkd-friedrich in `#4560 <https://github.com/TYPO3-Solr/ext-solr/pull/4560>`_
*   [TASK] Update test extensions to use 'apache-solr-for-typo3/solr' by @sfroemkenjw in `#4573 <https://github.com/TYPO3-Solr/ext-solr/pull/4573>`_
*   [TASK] Simplify unit tests configuration by @dkd-kaehm in `#4571 <https://github.com/TYPO3-Solr/ext-solr/pull/4571>`_
*   [TASK] Remove unused TYPO3 Core context initialization in integration tests by @sfroemkenjw in `#4568 <https://github.com/TYPO3-Solr/ext-solr/pull/4568>`_
*   [TASK] Update test extension path in IntegrationTestBase by @sfroemkenjw in `#4567 <https://github.com/TYPO3-Solr/ext-solr/pull/4567>`_
*   [TASK] Refactor DI handling for FrequentSearchesService by @sfroemkenjw in `#4548 <https://github.com/TYPO3-Solr/ext-solr/pull/4548>`_
*   [TASK] Refactor GroupItemPaginateViewHelper by @sfroemkenjw in `#4549 <https://github.com/TYPO3-Solr/ext-solr/pull/4549>`_
*   [BUGFIX] Adapt tests by @bmack in `#4546 <https://github.com/TYPO3-Solr/ext-solr/pull/4546>`_
*   [TASK] Remove PSR-14 event, and update RST file by @bmack in `#4546 <https://github.com/TYPO3-Solr/ext-solr/pull/4546>`_
*   [!!!][TASK] Remove site hash strategy flag by @bmack in `#4546 <https://github.com/TYPO3-Solr/ext-solr/pull/4546>`_
*   [BUGFIX] Polish infobox to align with current ContextualFeedbackSeverity by @aarends in `#4551 <https://github.com/TYPO3-Solr/ext-solr/pull/4551>`_
*   [TASK] Prepare v14 release notes by @dkd-friedrich in `#4547 <https://github.com/TYPO3-Solr/ext-solr/pull/4547>`_
*   [BUGFIX] Remove TSFE from access component by @garfieldius in `#4544 <https://github.com/TYPO3-Solr/ext-solr/pull/4544>`_
*   [BUGFIX] Allow GroupItemPaginateViewHelper template to be overridden by @jschlier in `#4542 <https://github.com/TYPO3-Solr/ext-solr/pull/4542>`_
*   [TASK] Replace removed FormResultCompiler with FormResultFactory for TYPO3 14 by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix access protected content indexing for TYPO3 14 by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Speed-up integration tests by skipping database initialization by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix integration tests for TYPO3 14 compatibility by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Make Item properties non-nullable with strict validation by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix FlexForm handling in SettingsPreviewOnPlugins for TYPO3 14 by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix TCA searchFields deprecation and ContentObjectRenderer for TYPO3 14 by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Refactor FrontendEnvironment/Tsfe to FrontendSimulation/FrontendAwareEnvironment by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix ViewHelper classes for TYPO3 14 / Fluid v5 compatibility by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix Report classes for TYPO3 14 compatibility by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Upgrade deps for TYPO3 14 by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Prepare schema/configset for dev-14.0.x by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Remove ext_econf.php file by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [BUGFIX] Respect plugin TS in RelevanceComponent by @helhum in `#4532 <https://github.com/TYPO3-Solr/ext-solr/pull/4532>`_
*   [BUGFIX] Catch InvalidArgumentException for missing site languages in GarbageHandler by @mwohlschlegel in `#4534 <https://github.com/TYPO3-Solr/ext-solr/pull/4534>`_
*   [BUGFIX] Add headers palette to solr plugin CType TCA definitions by @dkd-kaehm in `#4536 <https://github.com/TYPO3-Solr/ext-solr/pull/4536>`_
*   [BUGFIX] CS issues 2026.02.05 by @dkd-kaehm in `#4526 <https://github.com/TYPO3-Solr/ext-solr/pull/4526>`_
*   [DOCS] Update version matrix by @dkd-friedrich in `#4518 <https://github.com/TYPO3-Solr/ext-solr/pull/4518>`_
*   [SECURITY] Update to Apache Solr 9.10.1 by @dkd-friedrich in `#4518 <https://github.com/TYPO3-Solr/ext-solr/pull/4518>`_
*   [DOCS] Update version matrix in main for current versions by @dkd-kaehm in `91c455b8a <https://github.com/TYPO3-Solr/ext-solr/commit/91c455b8a015c89e4222ba6dd7a76984d303b406>`_


Contributors
============

.. note::
      239 -   Contributors will be listed here once the release is finalized.

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

- `Amir Arends <https://github.com/amirarends>`_
- `Benni Mack <https://github.com/bmack>`_
- `@garfieldius <https://github.com/garfieldius>`_
- `Helmut Hummel <https://github.com/helhum>`_
- `@jschlier <https://github.com/jschlier>`_
- `Markus Friedrich <https://github.com/dkd-friedrich>`_
- `Mikel Wohlschlegel <https://github.com/mikelwohlschlegel>`_
- `Philipp Kitzberger <https://github.com/kitzberger>`_
- `Rafael Kähm <https://github.com/dkd-kaehm>`_
- `Sascha Nowak <https://github.com/SaschaNoLe>`_
- `Stefan Frömken <https://github.com/sfroemkenjw>`_

Also a big thank you to our partners who have already concluded one of our new development participation packages such
as Apache Solr EB for TYPO3 14 LTS (Feature).


How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on `GitHub <https://github.com/TYPO3-Solr/ext-solr>`__
* Ask or help or answer questions in our `Slack channel <https://typo3.slack.com/messages/ext-solr/>`__
* Provide patches through Pull Request or review and comment on existing `Pull Requests <https://github.com/TYPO3-Solr/ext-solr/pulls>`__
* Go to `www.typo3-solr.com <https://www.typo3-solr.com>`__ or call `dkd <http://www.dkd.de>`__ to sponsor the ongoing development of Apache Solr for TYPO3

Support us by becoming an EB partner:

https://shop.dkd.de/Produkte/Apache-Solr-fuer-TYPO3/

or call:

+49 (0)69 - 2475218 0
