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
~~~~~~~~~~~~~~~~~~~~

All language files have been migrated from XLIFF 1.2 to XLIFF 2.0 format.

Parallel Solr Worker Cores for Integration Tests
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Integration tests now use parallel Solr worker cores via paratest, significantly
improving test execution speed.

Event Listener Migration to PHP Attributes
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Event listeners have been refactored to use the ``#[AsEventListener]`` PHP
attribute instead of ``Services.yaml`` tag registration, following TYPO3 14
best practices.


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


All Changes
-----------

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
