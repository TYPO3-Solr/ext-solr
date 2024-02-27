..  include:: /Includes.rst.txt
..  index:: Releases
.. _releases-11-2:

=============
Releases 11.2
=============

..  include:: HintAboutOutdatedChangelog.rst.txt


Release 11.2.4 ELTS
===================

..  note::
    Non public ELTS release, you can find more details on `typo3-solr.com <https://www.typo3-solr.com/solr-for-typo3/add-ons/typo3-10-elts-extended/>`__

Support of Apache Solr 9.5.0
----------------------------

Compatibility with Apache Solr 9.5.0 is checked and EXT:solr now accepts the following Apache Solr versions:

- 9.3.0
- 9.4.0
- 9.4.1
- 9.5.0

Small improvements and bugfixes
-------------------------------

- [BUGFIX] Handle float values in options facet parser by @dkd-kaehm in #11
- [BUGFIX:11.2] Exception with tx_solr_statistics after latest TYPO3 security update by @dkd-kaehm in #14
- !!![TASK] Update to Apache Solr 9.2 for TYPO3 10.4 ELTS by @dkd-kaehm in #19
- [TASK] Remove unused field `cookie` in tx_solr_statistics by @dkd-kaehm in #21
- [BUGFIX] Fix autosuggest with non-ascii terms by @dkd-friedrich in #28
- [TASK] Prepare release-11.2.x ELTS by @dkd-kaehm in #17
- [BUGFIX] Fix branch alias by @dkd-friedrich in #30
- [TASK] Allow custom ELTS repository by @dkd-friedrich in #30
- [BUGFIX:P:11.2] prevent undefined array key warning if filter is empty by Achim Fritz in #32
- [TASK] Allow Apache Solr 9.5 by @dkd-friedrich in #31

Release 11.2.3 - Last non ELTS release
======================================

This is a maintenance release for TYPO3 10.4 and the last non ELTS release, as TYPO3 10 LTS reaches the ELTS phase on April 30, 2023.

EXT:solr release-11.2.x will not be maintained in `TYPO3-Solr/ext-solr <https://github.com/TYPO3-Solr/ext-solr/>`__ repository any more. The maintenance and builds will be moved to a private
repository and ELTS versions, EXT:solr 11.2.4+ for TYPO3 10 ELTS versions, can be obtained through the `dkd EB program <https://shop.dkd.de/Produkte/Apache-Solr-fuer-TYPO3/>`__.

This release contains:

* [BUGFIX:P:11.2] make CE search form in backend editable again by @dkd-kaehm in `#3640 <https://github.com/TYPO3-Solr/ext-solr/pull/3640>`__
* [DOC] Fix wrong type for boostQuery in the docs and example by @rr-it  and @dkd-kaehm in `a997a2f4 <https://github.com/TYPO3-Solr/ext-solr/commit/a997a2f464462bc998aa755215f765e5efc6f172>`__

Release 11.2.2
==============

This is a maintenance release for TYPO3 10.4.

EXT:solr release-11.2.x will not be maintained in `TYPO3-Solr/ext-solr <https://github.com/TYPO3-Solr/ext-solr/>`__ repository any more. The maintenance and builds will be moved to a private
repository and ELTS versions, EXT:solr 11.2.3+ for TYPO3 10 ELTS versions, can be obtained through the `dkd EB program <https://shop.dkd.de/Produkte/Apache-Solr-fuer-TYPO3/>`__.

This release contains:

- [BUGFIX] Type-hinting for SiteUtility::getConnectionProperty() by @dkd-kaehm in #3395
- [TASK:Security:P:11.2] Update jQuery and its plugin libs by @dkd-kaehm in #3430
- [FEATURE] Add signal before search in resultsAction by @stat1x in #3391
- [BUGFIX:BP:11.2] Don't use minimum-stability dev on TYPO3 stable in build/CI by @dkd-kaehm in #3466
- [TASK] Docker version check on docker image build by @dkd-kaehm in #3524
- [BUGFIX:P:11.2] Use ConfigurationManager to get typscript in plugin FlexForm by @dkd-kaehm in #3497
- [TASK] Use PCOV for coverage instead of xDebug :: Upgrade to phpunit 8 by @dkd-kaehm in #3528
- [BUGFIX:BP:11.2] Make API eID script compatible with TYPO3 v11.5 by @dkd-friedrich in #3550
- [BUGFIX:BP:11.2] Use plugin namespace as label for flexforms by @dkd-friedrich in #3553
- [BUGFIX:BP:11.2] Respect indexingPriority in QueueItemRepository by @dkd-friedrich in #3557
- [BUGFIX:BP:11.2] use siteLanguage TypoScript object to get language id by @dkd-friedrich in #3555
- [TASK:11.2] equalize CI/Github-Actions for release-11.0|2|5 and main by @dkd-kaehm in #3467
- [BUGFIX:BP:11.2] Sanitize frequent and last searches output by @dkd-friedrich in #3592
- [BUGFIX:BP:11.2] changed from always picking the 0 array value to pic… by @dkd-friedrich in #3594
- [BUGFIX] Enforce visibility context in Tsfe by @saitho in #3050
- [TASK:BP:11.2] Disable sql handler by @dkd-friedrich in #3604


Release 11.2.1
==============

This is a maintenance release for TYPO3 10.4, containing:

- [BUGFIX] Prepend wrong fe language on empty cache (#3375)
- [TASK] Use TYPO3 IpAnonymizationUtility (#3262)
- [BUGFIX:BP:11.2] Shortcircuit work in SolrRoutingMiddleware (#3202)
- [TASK] Fix scrutinizer on release-11.2.x
- [BUGFIX:BP:11.2] Do not handle page updates on new page with uid 0 (#3338)
- [TASK] Remove not used strptime() adaption for windows.
- [BUGFIX] Respect indexing configuration for new and updated subpages (#3276)
- [BUGFIX] Ensure keywords string does not exceed database field length (#3321)
- [TASK:BP:11.2] Adapt column arrangement within sites config (#3295)
- [DOCS:BP:11.2] add missing doc for ..pageIndexed
- [BUGFIX] Fix PSR-4 Namespaces and Paths (#3285)
- [BUGFIX] Silence DebugWriter for PageIndexerRequest (#3030)
- [BUGFIX] AbstractSolrTask::setRootPageId(): Argument #1 must be of type int, string given (#3267)
- [BUGFIX:11.2] Update GarbageCollector.php (#3249)
- [DOCS] Align with new TYPO3 documentation standards (#3242)
- [DOCS] Align README.md with other extensions (#3218)
- [BUGFIX] Missing dot in configuration in numberOfResultsPerGroup method (#3098
- [TASK] Added info about using page content in fields
- [TASK] Added info about the virtual field __solr_contents
- [BUGFIX:BP:11.2] Fix write connection (#2916)


Release 11.2.0
==============

We are happy to release EXT:solr 11.2.0.
The focus of this release has been on supporting the latest Apache Solr version 8.11.1 and on optimizing the data update monitoring.

Apache Solr 8.11.1 support
--------------------------

With EXT:solr 11.2.0 we support Apache Solr 8.11.1, the latest release of Apache Solr.

To see what has changed in Apache Solr please read the release notes of Apache Solr:
https://solr.apache.org/docs/8_11_1/changes/Changes.html

Improved data update monitoring and handling
--------------------------------------------

To ensure the Solr index is up-to-date an extensive monitoring is done, in huge sites this may slow down the TYPO3 backend, as many records and
pages have to be checked and updated. With EXT:solr 11.2 you can configure how EXT:solr will monitor and handle data updates, by default EXT:solr
acts as in all former versions, but you can now configure a delayed update handling or even turn the monitoring off.


Small improvements and bugfixes
-------------------------------

Beside the major changes we did several small improvements and bugfixes:

- `[TASK] Upgrade Solarium to 6.0.4 .. <https://github.com/TYPO3-Solr/ext-solr/issues/3178>`__
- `[BUGFIX] Fix thrown exception in Synonym and StopWordParser .. <https://github.com/TYPO3-Solr/ext-solr/commit/300325221d9b4ec38b83b6d5e985d8d95ab1f9c5>`__
- `[BUGFIX] TER releases missing composer dependencies .. <https://github.com/TYPO3-Solr/ext-solr/issues/3176>`__
- `[TASK] Configure CI matrix for release 11.2 .. <https://github.com/TYPO3-Solr/ext-solr/commit/5a3843e191a2d3924412a43b54b48ba399e00036>`__
- `[BUGFIX:BP:11.1] Fix autosuggest with non-ascii terms .. <https://github.com/TYPO3-Solr/ext-solr/issues/3096>`__
- `[BUGFIX] Prevent unwanted filter parameters from being generated .. <https://github.com/TYPO3-Solr/ext-solr/issues/3126>`__
- `[TASK] Add Czech translation .. <https://github.com/TYPO3-Solr/ext-solr/issues/3132>`__
- `[TASK] Replace mirrors for Apache Solr binaries on install-solr.sh .. <https://github.com/TYPO3-Solr/ext-solr/issues/3094>`__
- `[BUGFIX:BP:11-1] routeenhancer with empty filters .. <https://github.com/TYPO3-Solr/ext-solr/issues/3099>`__
- `[TASK] Use Environment::getContext() instead of GeneralUtility .. <https://github.com/TYPO3-Solr/ext-solr/commit/7cde5222a6203ab97d353d8eca723fa3fa924e48>`__
- `[BUGFIX] Don't use jQuery.ajaxSetup() .. <https://github.com/TYPO3-Solr/ext-solr/issues/2503>`__
- `[TASK] Setup Github Actions :: Basics .. <https://github.com/TYPO3-Solr/ext-solr/commit/e545d692ce41133fcff8ec1d294b0a9d0e68bd2a>`__
- `[TASK] Setup Dependabot to watch "solarium/solarium" .. <https://github.com/TYPO3-Solr/ext-solr/commit/561815044e3651a0aaa8fa2ad4de5e2c3ccf4e3e>`__
- `[BUGFIX] Filter within route enhancers .. <https://github.com/TYPO3-Solr/ext-solr/issues/3054>`__
- `[BUGFIX] Fix NON-Composer mod libs composer.json for composer v2 <https://github.com/TYPO3-Solr/ext-solr/issues/3053>`__
- ... See older commits, which are a part of `previous releases <https://github.com/TYPO3-Solr/ext-solr/commits/main?after=d3f9a919d44f8a72b982bdde131408b571ff02c8+139&branch=release-11-2>`__


Contributors
============

Special thanks to ACO Ahlmann SE & Co. KG for sponsoring the improved data update handling, `#3153 <https://github.com/TYPO3-Solr/ext-solr/issues/3153>`__!

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Georg Ringer
* Lars Tode
* Mario Lubenka
* Markus Friedrich
* Marc Bastian Heinrichs
* Michael Wagner
* Rafael Kähm

Also a big thank you to our partners who have already concluded one of our new development participation packages such as Apache Solr EB for TYPO3 11 LTS (Feature), Apache Solr EB for TYPO3 10 LTS (Maintenance)
or Apache Solr EB for TYPO3 9 ELTS (Extended):

* ACO Ahlmann SE & Co. KG
* avenit AG
* b13 GmbH
* cyperfection GmbH
* in2code GmbH
* Leitgab Gernot
* medien.de mde GmbH
* TOUMORØ
* WIND Internet

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
