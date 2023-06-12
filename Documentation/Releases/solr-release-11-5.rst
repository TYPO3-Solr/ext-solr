.. include:: ../Includes.rst.txt


.. _releases-11-5:

==========================
Apache Solr for TYPO3 11.5
==========================

Apache Solr for TYPO3 11.5.3
============================

This is a maintenance release for TYPO3 11.5, containing:

- [BUGFIX] make CE search form in backend editable again by @rr-it in `#3626 <https://github.com/TYPO3-Solr/ext-solr/pull/3626>`__
- [DOC] Fix wrong type for boostQuery in the docs and example by @dkd-kaehm in `#3e7ff72 <https://github.com/TYPO3-Solr/ext-solr/commit/3e7ff72b7bc8ddd9cb7f5b7e998a328773483dfb>`__
- [TASK] Fix unit tests for 2023.06.07 by @dkd-kaehm in `#3695 <https://github.com/TYPO3-Solr/ext-solr/pull/3695>`__

Apache Solr for TYPO3 11.5.2
============================

This is a maintenance release for TYPO3 11.5, containing:

- [BUGFIX:BP:11.5] Fix error when indexing pages with field processing instruction categoryUidToHierarchy by @dkd-kaehm in `#3462 <https://github.com/TYPO3-Solr/ext-solr/pull/3462>`__
- [BUGFIX:BP:11.5] Custom doktype is deleted from solr after saving with custom queue configuration by @dkd-friedrich in `#3468 <https://github.com/TYPO3-Solr/ext-solr/pull/3468>`__
- [BUGFIX:BP:11.5] Don't use minimum-stability dev on TYPO3 stable in build/CI by @dkd-kaehm in `#3464 <https://github.com/TYPO3-Solr/ext-solr/pull/3464>`__
- [BUGFIX:BP:11.5] Fix value resolution in SOLR_RELATION by @dkd-friedrich in `#3484 <https://github.com/TYPO3-Solr/ext-solr/pull/3484>`__
- [FEATURE:P:11.5] Add new option manualSortOrderDelimiter for facets by @dkd-kaehm in `#3494 <https://github.com/TYPO3-Solr/ext-solr/pull/3494>`__
- [BUGFIX:P:11.5] Casting after check by @dkd-kaehm in `#3495 <https://github.com/TYPO3-Solr/ext-solr/pull/3495>`__
- [TASK] Docker version check on docker image build by @dkd-kaehm in `#3525 <https://github.com/TYPO3-Solr/ext-solr/pull/3525>`__
- [BUGFIX:P:11.5] Use ConfigurationManager to get typscript in plugin FlexForm by @dkd-kaehm in `#3496 <https://github.com/TYPO3-Solr/ext-solr/pull/3496>`__
- [BUGFIX:P:11.5] Exception on search plugin if no Solr connection is configured by @dkd-kaehm in `#3498 <https://github.com/TYPO3-Solr/ext-solr/pull/3498>`__
- [BUGFIX:BP:11.5] Fix handling of non existing pages on deletions by @dkd-friedrich in `#3520 <https://github.com/TYPO3-Solr/ext-solr/pull/3520>`__
- [TASK:BP:11] Verify the record before accessing the pid by @3l73 in `#3537 <https://github.com/TYPO3-Solr/ext-solr/pull/3537>`__
- [TASK:BP:11.5] Handle Solr connection exception by @dkd-friedrich in `#3542 <https://github.com/TYPO3-Solr/ext-solr/pull/3542>`__
- [BUGFIX:BP:11.5] Fix Solr response handling during indexing by @dkd-friedrich in `#3544 <https://github.com/TYPO3-Solr/ext-solr/pull/3544>`__
- [BUGFIX:BP:11.5] Use plugin namespace as label for flexforms by @dkd-friedrich in `#3552 <https://github.com/TYPO3-Solr/ext-solr/pull/3552>`__
- [BUGFIX:BP:11.5] Respect indexingPriority in QueueItemRepository by @dkd-friedrich in `#3556 <https://github.com/TYPO3-Solr/ext-solr/pull/3556>`__
- [BUGFIX:BP:11.5] add empty string as fallback by @dkd-friedrich in `#3559 <https://github.com/TYPO3-Solr/ext-solr/pull/3559>`__
- [BUGFIX:BP:11.5] use siteLanguage TypoScript object to get language id by @dkd-friedrich in `#3554 <https://github.com/TYPO3-Solr/ext-solr/pull/3554>`__
- [BUGFIX:BP:11.5] Sanitize frequent and last searches output by @dkd-friedrich in `#3591 <https://github.com/TYPO3-Solr/ext-solr/pull/3591>`__
- [BUGFIX:BP:11.5] changed from always picking the 0 array value to pic… by @dkd-friedrich in `#3593 <https://github.com/TYPO3-Solr/ext-solr/pull/3593>`__
- [BUGFIX:BP:11.5] Avoid getSolrConfiguration() on null by @dkd-friedrich in `#3599 <https://github.com/TYPO3-Solr/ext-solr/pull/3599>`__
- [TASK:BP:11.5] Disable sql handler by @dkd-friedrich in `#3603 <https://github.com/TYPO3-Solr/ext-solr/pull/3603>`__
- [BUGFIX:BP:11.5] Avoid PHP 8 warning when page indexing fails by @dkd-friedrich in `#3609 <https://github.com/TYPO3-Solr/ext-solr/pull/3609>`__
- [BUGFIX:BP:11.5] Ensure record exists before asserting if draft by @dkd-friedrich in `#3610 <https://github.com/TYPO3-Solr/ext-solr/pull/3610>`__
- [BUGFIX:BP:11.5] Fix usage of null coalescing operator by @dkd-friedrich in `#3611 <https://github.com/TYPO3-Solr/ext-solr/pull/3611>`__
- [BUGFIX:BP:11.5] return empty string for renderStatic if there is no content … by @dkd-friedrich in `#3612 <https://github.com/TYPO3-Solr/ext-solr/pull/3612>`__


Apache Solr for TYPO3 11.5.1
============================

We are happy to publish EXT:solr 11.5.1 maintenance release

New in this release
-------------------

- [BUGFIX] Do not include removed strptime() by @dkd-kaehm in https://github.com/TYPO3-Solr/ext-solr/pull/3335
- [BUGFIX:BP:11.5] Do not handle page updates on new page with uid 0 by @rr-it in https://github.com/TYPO3-Solr/ext-solr/pull/3344
- [BUGFIX:BP:11.5] Shortcircuit work in SolrRoutingMiddleware by @christophlehmann in https://github.com/TYPO3-Solr/ext-solr/pull/3341
- !!![TASK] Use preAddModifyDocuments  hook for pages by @christophlehmann in https://github.com/TYPO3-Solr/ext-solr/pull/3076
- [BUGFIX] Fix array key access in ext_getSetup (Backport 11.5) by @saitho in https://github.com/TYPO3-Solr/ext-solr/pull/3361
- [TASK:BP:11.5] Indexing configuration icon fallback by @dkd-friedrich in https://github.com/TYPO3-Solr/ext-solr/pull/3371
- [BUGFIX:BP:11.5] Do not index missing fields by @dkd-friedrich in https://github.com/TYPO3-Solr/ext-solr/pull/3372
- [TASK:BP:11.5] Introduce index queue type setting by @dkd-friedrich in https://github.com/TYPO3-Solr/ext-solr/pull/3370
- [TASK:BP:11.5] Do not index language with unconfigured core by @christophlehmann in https://github.com/TYPO3-Solr/ext-solr/pull/3373
- [BUGFIX] Make API eID script compatible with TYPO3 v11.5 by @peterkraume in https://github.com/TYPO3-Solr/ext-solr/pull/3350
- [BUGFIX] Type-hinting for SiteUtility::getConnectionProperty() by @dkd-kaehm in https://github.com/TYPO3-Solr/ext-solr/pull/3396
- [TASK:BP:11.5] Introduce generic EXT:solr exception by @dkd-friedrich in https://github.com/TYPO3-Solr/ext-solr/pull/3422
- [BUGFIX:BP:11.5] Fix frontend Solr connection initialization by @dkd-friedrich in https://github.com/TYPO3-Solr/ext-solr/pull/3425
- [ACTIONS:2022.12.22] Use fixed typo3/coding-standards 0.6.x < 0.7.0 for TYPO3 11.5 by @dkd-kaehm in https://github.com/TYPO3-Solr/ext-solr/pull/3429
- [TASK:Security] Update jQuery and its plugin libs by @dkd-kaehm in https://github.com/TYPO3-Solr/ext-solr/pull/3428
- [BUGFIX:P:11.5] Proper check for config.index_enable by @georgringer in https://github.com/TYPO3-Solr/ext-solr/pull/3433
- [BUGFIX:P:11.5] Typecast $timestamp to int in TimestampToUtcIsoDate by @derhansen in https://github.com/TYPO3-Solr/ext-solr/pull/3434
- [BUGFIX:P:11.5] prevent undefined array key warning if filter is empty by @achimfritz in https://github.com/TYPO3-Solr/ext-solr/pull/3435
- [FEATURE] Add signal before search in resultsAction by @stat1x in https://github.com/TYPO3-Solr/ext-solr/pull/3392
- [BUGFIX] Fix php warning undefined array key no_search_sub_entries by @DrWh0286 in https://github.com/TYPO3-Solr/ext-solr/pull/3381

Please read the release notes:
https://github.com/TYPO3-Solr/ext-solr/releases/tag/11.5.1

============================
Apache Solr for TYPO3 11.5.0
============================

We are happy to release EXT:solr 11.5.0.
The focus of this release has been on TYPO3 11 LTS compatibility.

#standwithukraine #nowar

**Important**: This version is installable with TYPO3 11 LTS on v11.5.14+ only and contains some breaking changes, see details below.

New in this release
-------------------

Support of TYPO3 11 LTS
~~~~~~~~~~~~~~~~~~~~~~~

With EXT:solr 11.5 we provide the support of TYPO3 11 LTS.

Please note that we require at least TYPO3 11.5.14, as this version contains some change concerning the usage of local TypoScriptFrontendController objects that are solving some issues during indexing.


Bootstrap 5.1
~~~~~~~~~~~~~

The default templates provided by EXT:solr were adapted for Bootstrap 5.1.

The templates are also prepared to display some icons with Bootstrap Icons, but the usage is optional and the icons are no longer provided with EXT:solr as the former Glyphicons were.


Custom field processors
~~~~~~~~~~~~~~~~~~~~~~~

fieldProcessingInstructions can be used for processing values during indexing, e.g. timestampToIsoDate or uppercase. Now you can register and use your own field processors via:
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['fieldProcessor']['yourFieldProcessor'] = ACustomFieldProcessor::class;

Custom processors have to implement interface ApacheSolrForTypo3\Solr\FieldProcessor\FieldProcessor.

N-Gram Filter for strings
~~~~~~~~~~~~~~~~~~~~~~~~~

Provides a new field type and dynamic fields for strings
with enabled Edge-N-Gram filter.

Now the following fields can be used:
- *_stringEdgeNgramS
- *_stringEdgeNgramM

Improve and Fix TSFE Initialization
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The initialization of TSFE within indexing and Backends modules contexts is refactored.

In this change the setting and usage of $GLOBALS['TSFE'] is removed and replaced by TYPO3s Core Context API.
The "Context" is always cloned instead of using its singleton instance.
The "Context", "Language", "TSFE" and "ServerRequest", which are required for TypoScript parsing in BE-modules and indexing contexts,
are highly isolated/capsuled and not visible anymore for all things not belonging to EXT:solr internals.

Byside of isolation/encapsulation of TSFE, the language handling is restored
to pure and default EXT:solr "fallback" mode approach.
So all page records, which are involved in indexing(All page types and above all all with sys_template or records-to-index), must be translated.
Otherwise the translation records will be indexed in default language.

Note: Since TYPO3 11 LTS does not allow to instantiate TSFE for sys folders and spacer,
      the initialization of TSFE will be done for first and closest page(not spacer or folder) within the site rootline.

Get "free content mode" working
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

In previous releases of EXT:solr the language handling for "free-content-mode" inconsistent.
The behavior of "free-content-mode" related records varied in RecordMonitor, Initializing and Indexing contexts,
which was the source of troubles for mixing overlay records in wrong cores/languages.

This change brings the RecordMonitor, Initializing and Indexing contexts for "free-content-mode" related records
into the same line, so the "free-content-mode" records are processed the same way.

Make pageRangeFirst and pageRangeLast accessible in fluid
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

With these two additional getters it is possible to access the variables
in fluid templates. See: `#3254 <https://github.com/TYPO3-Solr/ext-solr/issues/3254>`_

Add custom field processors
~~~~~~~~~~~~~~~~~~~~~~~~~~~

Custom field processors can be registered with

.. code-block:: php
   $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['fieldProcessor']['yourFieldProcessor'] = ACustomFieldProcessor::class;

And many more
~~~~~~~~~~~~~

Please see the list of changes below or `the full changelog: <https://github.com/TYPO3-Solr/ext-solr/compare/c0a3e62053e1c929c914d25ced1fef3d9868d4f9...11.5.0>`_.

The list of all changes:
~~~~~~~~~~~~~~~~~~~~~~~~

- [TASK] Prepare schemas for EXT:solr 11.5.x `c0a3e6205 <https://github.com/TYPO3-Solr/ext-solr/commit/c0a3e6205>`_
- [TASK] Provide N-Gram Filter for strings `13b90a996 <https://github.com/TYPO3-Solr/ext-solr/commit/13b90a996>`_
- [TASK] composer branch aliases `ebfee76bb <https://github.com/TYPO3-Solr/ext-solr/commit/ebfee76bb>`_
- [BUGFIX] Recursive constants `8af25d03f <https://github.com/TYPO3-Solr/ext-solr/commit/8af25d03f>`_
- [BUGFIX:BP:11.5] Follow up to recursive constants `a57960763 <https://github.com/TYPO3-Solr/ext-solr/commit/a57960763>`_
- [TASK] Migrate TYPO3#88366 deprecated `cache_` prefix on caches `a8f111592 <https://github.com/TYPO3-Solr/ext-solr/commit/a8f111592>`_
- [BUGFIX] Filter within route enhancers `b6d77ee52 <https://github.com/TYPO3-Solr/ext-solr/commit/b6d77ee52>`_
- [BUGFIX] Fix NON-Composer mod libs composer.json for composer v2 `e9ec5c11c <https://github.com/TYPO3-Solr/ext-solr/commit/e9ec5c11c>`_
- [TASK] Setup Dependabot to watch "solarium/solarium" `dfc99f4b0 <https://github.com/TYPO3-Solr/ext-solr/commit/dfc99f4b0>`_
- [TASK] Setup Github Actions :: Basics `ceb892408 <https://github.com/TYPO3-Solr/ext-solr/commit/ceb892408>`_
- [TASK] Bump to and test against TYPO3 ^11.5 `e7eeb2b3d <https://github.com/TYPO3-Solr/ext-solr/commit/e7eeb2b3d>`_
- [TASK] Bump solarium to 6.1.4, which supports PHP 8.0 `e56c32436 <https://github.com/TYPO3-Solr/ext-solr/commit/e56c32436>`_
- [TASK] Bump nimut/testing-framework to v. 6.0, which supports PHP 8.0 `e5353ab3c <https://github.com/TYPO3-Solr/ext-solr/commit/e5353ab3c>`_
- [FIX] Fix GH actions on branches push event `85e413d39 <https://github.com/TYPO3-Solr/ext-solr/commit/85e413d39>`_
- [BUGFIX:P:11.5] Don't use jQuery.ajaxSetup() `6714590a8 <https://github.com/TYPO3-Solr/ext-solr/commit/6714590a8>`_
- [TASK] Restructure version matrix `9535750f4 <https://github.com/TYPO3-Solr/ext-solr/commit/9535750f4>`_
- [Bugfix:BP:11-5] routeenhancer with empty filters `578e0153b <https://github.com/TYPO3-Solr/ext-solr/commit/578e0153b>`_
- [TASK:11.5] Replace mirrors for Apache Solr binaries on install-solr.sh `7f998d221 <https://github.com/TYPO3-Solr/ext-solr/commit/7f998d221>`_
- [TASK] Make TYPO3 11 LTS compatible : Backend Modules Templates `871c5b00f <https://github.com/TYPO3-Solr/ext-solr/commit/871c5b00f>`_
- [TASK] Make TYPO3 11 LTS compatible : rector run `7e104a499 <https://github.com/TYPO3-Solr/ext-solr/commit/7e104a499>`_
- Make TYPO3 11 LTS compatible : TSFE initialization : record indexing `66f512b12 <https://github.com/TYPO3-Solr/ext-solr/commit/66f512b12>`_
- [TASK] Make collapse work in BE `800384e48 <https://github.com/TYPO3-Solr/ext-solr/commit/800384e48>`_
- [TASK] Style index fields tab in info module `8f9a0ce9d <https://github.com/TYPO3-Solr/ext-solr/commit/8f9a0ce9d>`_
- [TASK] Fix loading Chart module `8fd1182ac <https://github.com/TYPO3-Solr/ext-solr/commit/8fd1182ac>`_
- [TASK] Adapt namespaces `f1f5521b9 <https://github.com/TYPO3-Solr/ext-solr/commit/f1f5521b9>`_
- !!! [TASK] Switch to hook contentPostProc-cached of TypoScriptFrontendController `e1c8c3afc <https://github.com/TYPO3-Solr/ext-solr/commit/e1c8c3afc>`_
- [TASK] Apply rectors `0e6bf902e <https://github.com/TYPO3-Solr/ext-solr/commit/0e6bf902e>`_
- [BUGFIX] Enforce visibility context in Tsfe `d50947375 <https://github.com/TYPO3-Solr/ext-solr/commit/d50947375>`_
- [TASK] Fix scrutinizer for EXT:Solr 11.5 `43dcbd43f <https://github.com/TYPO3-Solr/ext-solr/commit/43dcbd43f>`_
- [TASK-11.5C] Fix - "Unit" Tests : Remove usages of UriBuilder::setUseCacheHash() `d71ec451c <https://github.com/TYPO3-Solr/ext-solr/commit/d71ec451c>`_
- [TASK-11.5C] Fix - "Unit" Tests `5e047c520 <https://github.com/TYPO3-Solr/ext-solr/commit/5e047c520>`_
- [TASK-11.5C] Fix - "Unit" Tests : PHP 8.0 `6023d78d7 <https://github.com/TYPO3-Solr/ext-solr/commit/6023d78d7>`_
- [TASK] Let PHP 8.0 Job allow to fail temporarily `d36c22e3e <https://github.com/TYPO3-Solr/ext-solr/commit/d36c22e3e>`_
- TBD!!! [TASK-11.5C] Fix - "Integration" Tests `4005e974b <https://github.com/TYPO3-Solr/ext-solr/commit/4005e974b>`_
- !!![TASK] Improve and Fix TSFE Initialization `a246cb8e3 <https://github.com/TYPO3-Solr/ext-solr/commit/a246cb8e3>`_
- [TASK] Refactor IntegrationTest base class : auto import root pages `d14b82ec5 <https://github.com/TYPO3-Solr/ext-solr/commit/d14b82ec5>`_
- [TASK] Refactor Integration tests : SiteHashServiceTest `280271d04 <https://github.com/TYPO3-Solr/ext-solr/commit/280271d04>`_
- [TASK] Refactor Integration tests : ResultSetReconstitutionProcessorTest `1317a2792 <https://github.com/TYPO3-Solr/ext-solr/commit/1317a2792>`_
- [TASK] Refactor Integration tests : IndexerTest `f87a5f5d7 <https://github.com/TYPO3-Solr/ext-solr/commit/f87a5f5d7>`_
- [TASK] Refactor Integration tests : IndexerTest additionalPageIds `723ccea67 <https://github.com/TYPO3-Solr/ext-solr/commit/723ccea67>`_
- [TASK] Refactor Integration tests : IndexerTest "hide default language" `1538c61dc <https://github.com/TYPO3-Solr/ext-solr/commit/1538c61dc>`_
- [TASK] Refactor Integration tests : IndexerTest "Relation (MM) translation overlays" `82bfe55d4 <https://github.com/TYPO3-Solr/ext-solr/commit/82bfe55d4>`_
- [TASK] Reactivate tests for indexing records without L parameter `66afd4f59 <https://github.com/TYPO3-Solr/ext-solr/commit/66afd4f59>`_
- [TASK] Refactor Integration tests : Schrink fixtures `25cf5b911 <https://github.com/TYPO3-Solr/ext-solr/commit/25cf5b911>`_
- [BUGFIX] Remove hidden translated record in index `1b7642115 <https://github.com/TYPO3-Solr/ext-solr/commit/1b7642115>`_
- [FEATURE] Get "free content mode" working `0986a24c9 <https://github.com/TYPO3-Solr/ext-solr/commit/0986a24c9>`_
- [BUGFIX] TypoScript configuration for "Hide default language" sites `ddcbc3bb6 <https://github.com/TYPO3-Solr/ext-solr/commit/ddcbc3bb6>`_
- [TASK] Refactor pagination `bb42410af <https://github.com/TYPO3-Solr/ext-solr/commit/bb42410af>`_
- [TASK] Fix indentation, add more documentation `56922bdb4 <https://github.com/TYPO3-Solr/ext-solr/commit/56922bdb4>`_
- [TASK] Fix Index-Queue module: "Clear Index Queue" functionality `1307974e9 <https://github.com/TYPO3-Solr/ext-solr/commit/1307974e9>`_
- [TASK] Disable temporary testing against TYPO3 < v11.5.4 `9faf73fb6 <https://github.com/TYPO3-Solr/ext-solr/commit/9faf73fb6>`_
- [TASK] Refactor LastSearches and FrequentlySearched widgets `b3a9fef4c <https://github.com/TYPO3-Solr/ext-solr/commit/b3a9fef4c>`_
- [TASK] Allow to publish (-PRE)-(ALPHA|BETA|RC) releases to TYPO3 TER `5cb71c168 <https://github.com/TYPO3-Solr/ext-solr/commit/5cb71c168>`_
- [FIX] Allow to edit pages outside of site root `6c8801154 <https://github.com/TYPO3-Solr/ext-solr/commit/6c8801154>`_
- [FIX] Allow to mark pages as site root `09009909b <https://github.com/TYPO3-Solr/ext-solr/commit/09009909b>`_
- [FIX] Don't auto select first configured solr site if non configured exists `352998671 <https://github.com/TYPO3-Solr/ext-solr/commit/352998671>`_
- [FIX] Can't create SchedulerTask `05ae55ec7 <https://github.com/TYPO3-Solr/ext-solr/commit/05ae55ec7>`_
- [TASK] Add Czech translation `a3805b287 <https://github.com/TYPO3-Solr/ext-solr/commit/a3805b287>`_
- [Bugfix] Prevent unwanted filter parameters from being generated `3e156981d <https://github.com/TYPO3-Solr/ext-solr/commit/3e156981d>`_
- !!![TASK] Refactor Site stack `5120a68b7 <https://github.com/TYPO3-Solr/ext-solr/commit/5120a68b7>`_
- !!![FIX] Index Queue initialization is not robust enought `bc7133237 <https://github.com/TYPO3-Solr/ext-solr/commit/bc7133237>`_
- [FIX] typoscript in Tsfe::initializeTsfe()  parsed twice `aafc18de3 <https://github.com/TYPO3-Solr/ext-solr/commit/aafc18de3>`_
- [FIX] Integration tests on release-11.5.x `210a64a88 <https://github.com/TYPO3-Solr/ext-solr/commit/210a64a88>`_
- [TASK] Upgrade to Apache Solr 8.11.1 `b3ab72de1 <https://github.com/TYPO3-Solr/ext-solr/commit/b3ab72de1>`_
- [BUGFIX] Catch Throwables instead Exceptions `a2988d2ff <https://github.com/TYPO3-Solr/ext-solr/commit/a2988d2ff>`_
- [FEATURE] Fix #3143: improve variant handling by sorting user groups `e38785eb8 <https://github.com/TYPO3-Solr/ext-solr/commit/e38785eb8>`_
- [BUGFIX] Fix #3145: exception in scheduler with php 8 `75b1237e0 <https://github.com/TYPO3-Solr/ext-solr/commit/75b1237e0>`_
- [BUGFIX] Fix #3141: TypeError in TranslateViewHelper `bc12bfafd <https://github.com/TYPO3-Solr/ext-solr/commit/bc12bfafd>`_
- [BUGFIX] Fix autosuggest with non-ascii terms `6687bcd4f <https://github.com/TYPO3-Solr/ext-solr/commit/6687bcd4f>`_
- Allow to generate indexing error log from throwable `4abdba3f3 <https://github.com/TYPO3-Solr/ext-solr/commit/4abdba3f3>`_
- [FIX] Can't index pages which require a user session `2e35a8c05 <https://github.com/TYPO3-Solr/ext-solr/commit/2e35a8c05>`_
- [CLEANUP] Remove unused "Initialize Solr connections" code `bc03310cc <https://github.com/TYPO3-Solr/ext-solr/commit/bc03310cc>`_
- [TASK] Make FE/Search tests working `14c45a210 <https://github.com/TYPO3-Solr/ext-solr/commit/14c45a210>`_
- [TASK] Remove IntegrationTest::importDumpFromFixture() method `b7e4c6f59 <https://github.com/TYPO3-Solr/ext-solr/commit/b7e4c6f59>`_
- [TASK] make scrutinizer ocular working on PHP 8+ `e58050fb4 <https://github.com/TYPO3-Solr/ext-solr/commit/e58050fb4>`_
- [FIX] Call to undefined method ResponseFactory::createJsonResponse() `6b65feccb <https://github.com/TYPO3-Solr/ext-solr/commit/6b65feccb>`_
- [TASK] Fix Scrutinizer issues `d40bcd67a <https://github.com/TYPO3-Solr/ext-solr/commit/d40bcd67a>`_
- [BUGFIX] Prevent some "undefined array key" warnings with php 8 `5a4ef9038 <https://github.com/TYPO3-Solr/ext-solr/commit/5a4ef9038>`_
- [BUGFIX] TER releases missing composer dependencies `be3eafc0d <https://github.com/TYPO3-Solr/ext-solr/commit/be3eafc0d>`_
- [TASK] unite all intgeration tests in same suite `a227fe7f9 <https://github.com/TYPO3-Solr/ext-solr/commit/a227fe7f9>`_
- [TASK] Test TYPO3 11+ with PHP 8.1 as well `4be1ccc5f <https://github.com/TYPO3-Solr/ext-solr/commit/4be1ccc5f>`_
- [TASK:11.5] Upgrade solarium/solarium to 6.6.2 `efe7c5614 <https://github.com/TYPO3-Solr/ext-solr/commit/efe7c5614>`_
- [WIP] PHP 8.1 compatibility `15c1221e5 <https://github.com/TYPO3-Solr/ext-solr/commit/15c1221e5>`_
- [BUGFIX] Fix notice in TranslateViewHelper `3b91901e6 <https://github.com/TYPO3-Solr/ext-solr/commit/3b91901e6>`_
- [TASK] Avoid different Solarium versions in non- and composer modes `4091c6261 <https://github.com/TYPO3-Solr/ext-solr/commit/4091c6261>`_
- [P:11.5:FEATURE] Improve data update handling `6561e3585 <https://github.com/TYPO3-Solr/ext-solr/commit/6561e3585>`_
- [TASK] Add proper annotations on GH actions job failures. `f145285e2 <https://github.com/TYPO3-Solr/ext-solr/commit/f145285e2>`_
- [TASK] Migrate to PhpUnit 9+ Api and cleanup the obsolete method mocks `cc8cc7885 <https://github.com/TYPO3-Solr/ext-solr/commit/cc8cc7885>`_
- [BUGFIX] Fix write connection `9a16a743d <https://github.com/TYPO3-Solr/ext-solr/commit/9a16a743d>`_
- [BUGFIX] core optimization module PHP 8.1 compatibility `c81407540 <https://github.com/TYPO3-Solr/ext-solr/commit/c81407540>`_
- [TASK] Remove not used `strptime()` adaption for windows. `ad5c03932 <https://github.com/TYPO3-Solr/ext-solr/commit/ad5c03932>`_
- [BUGFIX] Ensure BE_USER is kept when initializing TSFE `c7c0ba8ad <https://github.com/TYPO3-Solr/ext-solr/commit/c7c0ba8ad>`_
- [TASK:11.5] Minimal changes to Templates to make Bootstrap 5.1 working `d5940d393 <https://github.com/TYPO3-Solr/ext-solr/commit/d5940d393>`_
- [TASK] Standardize *.php files header declaration `514717864 <https://github.com/TYPO3-Solr/ext-solr/commit/514717864>`_
- [TASK] Use and apply TYPO3 coding standards, rector and type hinting `61076e3ed <https://github.com/TYPO3-Solr/ext-solr/commit/61076e3ed>`_
- [BUGFIX] Skip rootline check in be for records stored at pid 0 `6800394c0 <https://github.com/TYPO3-Solr/ext-solr/commit/6800394c0>`_
- [BUGFIX] Prevent "undefined array key" warnings with php 8 in page indexer `d4afa18d1 <https://github.com/TYPO3-Solr/ext-solr/commit/d4afa18d1>`_
- FIX: Argument 1 passed to ApacheSolrForTypo3\Solr\Task\AbstractSolrTask::setRootPageId() must be of the type int, string given `2858e45aa <https://github.com/TYPO3-Solr/ext-solr/commit/2858e45aa>`_
- Added info about the virtual field __solr_contents `8002707ed <https://github.com/TYPO3-Solr/ext-solr/commit/8002707ed>`_
- Added info about using page content in fields `85741400b <https://github.com/TYPO3-Solr/ext-solr/commit/85741400b>`_
- FIX: Argument 1 passed to ApacheSolrForTypo3\Solr\System\Url\UrlHelper::setPort() must be of the type int, string given `9afe701ad <https://github.com/TYPO3-Solr/ext-solr/commit/9afe701ad>`_
- [TASK] Bootstrap 5.1 adaptions `0d6f62a30 <https://github.com/TYPO3-Solr/ext-solr/commit/0d6f62a30>`_
- [CLEANUP] Delete obsolete TypoScript example ConnectionFromConfVars `cb5b5284a <https://github.com/TYPO3-Solr/ext-solr/commit/cb5b5284a>`_
- [BUGFIX] Ensure proper items per page setting `84d70b1f1 <https://github.com/TYPO3-Solr/ext-solr/commit/84d70b1f1>`_
- [BUGFIX:11.5]  Access restricted pages can not be indexed on TYPO3 11.5 `74d316358 <https://github.com/TYPO3-Solr/ext-solr/commit/74d316358>`_
- [BUGFUX] Fix #3221: exception in page browser `094e70fa6 <https://github.com/TYPO3-Solr/ext-solr/commit/094e70fa6>`_
- [TASK] Follow-up changes by EXT:solrfal for TYPO3 11.5 `41ac7ffd5 <https://github.com/TYPO3-Solr/ext-solr/commit/41ac7ffd5>`_
- [TASK] Supress warnings of strftime `fbf20c41d <https://github.com/TYPO3-Solr/ext-solr/commit/fbf20c41d>`_
- [TASK] Remove unnecessary bootstrap_package `1582b646f <https://github.com/TYPO3-Solr/ext-solr/commit/1582b646f>`_
- [TASK] Reenable skipped test of SearchControllerTest `1e0be7a51 <https://github.com/TYPO3-Solr/ext-solr/commit/1e0be7a51>`_
- [BUGFIX] Fix feuser initialisation in BE context `3ea33b4f8 <https://github.com/TYPO3-Solr/ext-solr/commit/3ea33b4f8>`_
- [TASK] Improve error handling in index queue module `cb0292d6f <https://github.com/TYPO3-Solr/ext-solr/commit/cb0292d6f>`_
- [BUGFIX] Add type cast to TaskProviders `ab070482e <https://github.com/TYPO3-Solr/ext-solr/commit/ab070482e>`_
- [BUGFIX] Missing dot in configuration in numberOfResultsPerGroup method `59a49ba41 <https://github.com/TYPO3-Solr/ext-solr/commit/59a49ba41>`_
- [DOCS] Align README.md with other extensions (#3218) `9b4a1153b <https://github.com/TYPO3-Solr/ext-solr/commit/9b4a1153b>`_
- [DOCS] Align with new TYPO3 documentation standards (#3242) `ec66f49e5 <https://github.com/TYPO3-Solr/ext-solr/commit/ec66f49e5>`_
- [TASK] Prevent type errors `061ef243a <https://github.com/TYPO3-Solr/ext-solr/commit/061ef243a>`_
- [TASK] Allow SearchResultSetService instantiation via makeInstance `b15f2444e <https://github.com/TYPO3-Solr/ext-solr/commit/b15f2444e>`_
- [TASK] Move ObjectManager to constructor in AbstractFacet `35405f349 <https://github.com/TYPO3-Solr/ext-solr/commit/35405f349>`_
- [FEATURE] Make pageRangeFirst and pageRangeLast accessible in fluid `31ba843a1 <https://github.com/TYPO3-Solr/ext-solr/commit/31ba843a1>`_
- [BUGFIX] Fix return type error for option facet `002661140 <https://github.com/TYPO3-Solr/ext-solr/commit/002661140>`_
- [BUGFIX] change detection of free mode records `eb87e83ba <https://github.com/TYPO3-Solr/ext-solr/commit/eb87e83ba>`_
- [BUGFIX] Avoid yoda-style conditions in PHP `48e52dbd0 <https://github.com/TYPO3-Solr/ext-solr/commit/48e52dbd0>`_
- [TASK] Sync with new TYPO3 coding standards `b15838961 <https://github.com/TYPO3-Solr/ext-solr/commit/b15838961>`_
- [TASK] Sync with EXT:solrfluidgrouping for TYPO3 11.5 `1ef155471 <https://github.com/TYPO3-Solr/ext-solr/commit/1ef155471>`_
- Update GarbageCollector.php `eab5887f1 <https://github.com/TYPO3-Solr/ext-solr/commit/eab5887f1>`_
- [BUGFIX] AbstractSolrTask::setRootPageId(): Argument #1 () must be of type int, string given `506b540e4 <https://github.com/TYPO3-Solr/ext-solr/commit/506b540e4>`_
- Silence DebugWriter for PageIndexerRequest `56203dfa0 <https://github.com/TYPO3-Solr/ext-solr/commit/56203dfa0>`_
- [BUGFIX] Undefined array key in ..Domain\Site\Site:L130 `8e1d5ed0e <https://github.com/TYPO3-Solr/ext-solr/commit/8e1d5ed0e>`_
- [BUGFIX] Fix PSR-4 Namesppaces and Paths `49a797884 <https://github.com/TYPO3-Solr/ext-solr/commit/49a797884>`_
- [BUGFIX] Ensure array value is set when accessing `3fa4ff496 <https://github.com/TYPO3-Solr/ext-solr/commit/3fa4ff496>`_
- [BUGFIX:11.5] Frequent Searches plugin does not work `49b32a195 <https://github.com/TYPO3-Solr/ext-solr/commit/49b32a195>`_
- [BUGFIX] Class properties must not be accessed before initialization `5a9556488 <https://github.com/TYPO3-Solr/ext-solr/commit/5a9556488>`_
- [BUGFIX] Respect indexing configuration for new and updated subpages `6196913be <https://github.com/TYPO3-Solr/ext-solr/commit/6196913be>`_
- [BUGFIX:BP:11.5] Empty suggest query triggers a PHP error `f564a31b9 <https://github.com/TYPO3-Solr/ext-solr/commit/f564a31b9>`_
- [TASK:BP:11.5] Adjust typo3/coding-standards settings `c0b0e1a6f <https://github.com/TYPO3-Solr/ext-solr/commit/c0b0e1a6f>`_
- [DOCS:BP:11.5] add missing doc for plugin.tx_solr.logging.indexing.pageIndexed `e309f0f9f <https://github.com/TYPO3-Solr/ext-solr/commit/e309f0f9f>`_
- [TASK:BP:11.5] Require TYPO3 11.5.14 `b698f86e9 <https://github.com/TYPO3-Solr/ext-solr/commit/b698f86e9>`_
- [TASK:BP:11.5] Adapt column arrangement within sites config `bd628be99 <https://github.com/TYPO3-Solr/ext-solr/commit/bd628be99>`_
- [FEATURE:BP:11.5] Add custom field processors `173c7a5d4 <https://github.com/TYPO3-Solr/ext-solr/commit/173c7a5d4>`_
- [TASK:11.5] Fix TYPO3 coding standards issues after upgrade to v0.5.5 `55830f209 <https://github.com/TYPO3-Solr/ext-solr/commit/55830f209>`_
- Ensure keywords string does not exceed database field length `9f2c81768 <https://github.com/TYPO3-Solr/ext-solr/commit/9f2c81768>`_
- [BUG] make sure that $currentPageNumber in resultsAction is always >= 1 (#3324) `be8cc90b6 <https://github.com/TYPO3-Solr/ext-solr/commit/be8cc90b6>`_
- [FEATURE] add logging for failed http requests `f9edd8bc4 <https://github.com/TYPO3-Solr/ext-solr/commit/f9edd8bc4>`_
- [BUGFIX] fix infinite loop in Tsfe::getPidToUseForTsfeInitialization() `3a2b8d0e8 <https://github.com/TYPO3-Solr/ext-solr/commit/3a2b8d0e8>`_


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Achim Fritz
* Andreas Beutel
* Andreas Kießling
* ayacoo
* Christoph Lehmann
* Christopher Schnell
* Daniel Koether
* dev-rke
* Dmitry Dulepov
* dsone
* FearFreddy
* Georg Ringer
* garfieldius
* Guido Schmechel
* Henrik Elsner
* Jan Delius
* Jens Jacobsen
* Lars Tode
* leslawp
* Marc Bastian Heinrichs
* Mario Lubenka
* Marcus Balasch
* Marcus Schwemer
* Markus Friedrich
* Markus Kobligk
* Michael Kettel
* Michael Wagner
* Michiel Roos
* Nicola Widmer
* Pascal Hofmair
* Peter, CyberForum e.V
* Peter Kraume
* Philipp Kitzberger
* Rafael Kähm
* René Maas
* Rudy Gnodde
* rr-it
* Sascha Egerer
* Sebastian Hofer
* Sebastian Michaelsen
* Soren Malling
* stat1x
* Stefan Frömken
* Stefano Kowalke
* twojtylak
* Thomas Löffler
* Tobias Kretschmann
* Tobias Schmidt
* Torben Hansen


Also a big thank you to our partners who have already concluded one of our new development participation packages such as Apache Solr EB for TYPO3 11 LTS (Feature), Apache Solr EB for TYPO3 10 LTS (Maintenance)
or Apache Solr EB for TYPO3 9 ELTS (Extended):

* .hausformat GmbH
* ACO Ahlmann SE & Co. KG
* AgenturWebfox GmbH
* Amedick & Sommer Neue Medien GmbH
* avenit AG
* b13 GmbH
* Bytebetrieb GmbH & Co. KG
* Cobytes B.V.
* Connetation Web Engineering GmbH
* cosmoblonde GmbH
* creativ clicks GmbH
* cyperfection GmbH
* DVT - Daten-Verarbeitung-Tirol GmbH
* Earlybird GmbH & Co KG
* elancer-team GmbH
* eulenblick Kommunikation und Werbung
* FONDA GmbH
* GFE Media GmbH
* graphodata GmbH
* Hirsch & Wölfl GmbH
* Hochschule Niederrhein
* i-fabrik GmbH
* in2code GmbH
* internezzo ag
* Intersim AG
* IW Medien GmbH
* Jochen Weiland
* Kassenärztliche Vereinigung Rheinland-Pfalz
* Kreis Euskirchen
* Landeskriminalamt Thüringen
* L.N. Schaffrath DigitalMedien GmbH
* Leitgab Gernot
* LOUIS INTERNET GmbH
* Marketing Factory Consulting GmbH
* medien.de mde GmbH
* MEDIA::ESSENZ
* mehrwert intermediale kommunikation GmbH
* Neue Medien GmbH
* NEW.EGO GmbH
* novotegra GmbH
* Overlap GmbH & Co KG
* Pädagogische Hochschule Karlsruhe
* peytz.dk
* ProPotsdam GmbH
* Proud Nerds
* Provitex GmbH
* queo GmbH
* Québec.ca
* rms. relationship marketing solutions GmbH
* Sandstein Neue Medien GmbH
* Schoene neue kinder GmbH
* seam media group gmbh
* SITE'NGO
* Snowflake Productions GmbH
* SOS Software Service GmbH
* Stämpfli AG
* Studio 9 GmbH
* systime.dk
* techniConcept Sàrl
* TOUMORØ
* Typoheads GmbH
* UEBERBIT GmbH
* visol digitale Dienstleistungen GmbH
* WACON Internet GmbH
* we.byte GmbH
* wegewerk GmbH
* werkraum Digitalmanufaktur GmbH
* WIND Internet
* zimmer7 GmbH

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


