..  include:: /Includes.rst.txt
..  index:: Releases
.. _releases-11:

=============
Releases 11.0
=============

..  include:: HintAboutOutdatedChangelog.rst.txt

Release 11.0.9 ELTS
===================

We are happy to release EXT:solr 11.0.9 maintenance release, this release is a non-public ELTS release.

..  note::
    Non public ELTS release, you can find more details on `typo3-solr.com <https://www.typo3-solr.com/solr-for-typo3/add-ons/typo3-10-elts-extended/>`__

Support of Apache Solr 9.5.0
----------------------------

Compatibility with Apache Solr 9.5.0 is checked and EXT:solr now accepts the following Apache Solr versions:

- 9.2.0
- 9.2.1
- 9.3.0
- 9.4.0
- 9.4.1
- 9.5.0

To avoid breaking changes EXT:solr 11.0.9 doesn't require Solr 9.5.0, but it's strongly recommended to use the latest supported version.

Note that due to CVE-2023-50290 you should at least check your configuration or update to at least Apache Solr 9.3.0.

Small improvements and bugfixes
-------------------------------

- [TASK:BP:11.0] Prepend wrong fe language on empty cache by @goldi42
- [BUGFIX:BP:11.0] Fix connection initialization by @dkd-friedrich in #10
- [BUGFIX] Handle float values in options facet parser by @dkd-kaehm in #12
- [BUGFIX:11.0] Exception with tx_solr_statistics after latest TYPO3 security update by @dkd-kaehm in #13
- [TASK] Remove unused field `cookie` in tx_solr_statistics by @dkd-kaehm in #22
- [TASK] Allow Apache Solr 9.4 by @dkd-friedrich in #25
- [BUGFIX] Fix result highlighting fragment size by @dkd-friedrich in #26
- [TASK] Allow Apache Solr 9.5 by @dkd-friedrich in #27


Release 11.0.8 ELTS
===================

We are happy to release EXT:solr 11.0.8 maintenance release, this release is a non-public ELTS release.

..  note::
    Non public ELTS release, you can find more details on `typo3-solr.com <https://www.typo3-solr.com/solr-for-typo3/add-ons/typo3-10-elts-extended/>`__

Support of Apache Solr 9.2.0
----------------------------

Apache Solr 8.5.1 is outdated, thus we now provide support of Apache Solr 9.2. Technically an update is not required, but recommended to avoid possible
security issues in no longer supported Apache Solr versions. If you can't update your Apache Solr server right now, EXT:solr 11.0.8 should still work with 8.5,
but you'll see warnings in the reports module.

Note: With Apache Solr 9 the following components are no longer available and you have to adapt the configuration if needed. No longer available components are:

1) Data Import Handler (DIH)
DIH is an independent project now; it is no longer a part of Solr

2) VelocityResponseWriter
VelocityResponseWriter is an independent project now; it is no longer a part of Solr. This encompasses all previously included /browse and wt=velocity examples.

Small improvements and bugfixes
-------------------------------

- [TASK:Security:P:ELTS_9.5] Update jQuery and its plugin libs by @dkd-kaehm in #3
- [BUGFIX:BP:11.0] Respect indexingPriority in QueueItemRepository by @dkd-friedrich in #5
- [TASK] Integrate in packagist.com : conflict with non ELTS EXTsolr by @dkd-kaehm in #4
- [BUGFIX:BP:11.0] Sanitize frequent and last searches output by @dkd-friedrich in #6
- [BUGFIX] Fix TypeError in StatisticsWriterProcessor by @dkd-friedrich in #6
- [TASK] Remove unneeded GitHub actions by @dkd-friedrich in #6
- [TASK] Remove unneeded TYPO3 version from matrix  by @dkd-friedrich in #6
- [BUGFIX] Try to solve conflicts issue on composer on CI by @dkd-kaehm in #7
- [TASK] Prepare configsets for 11.0 ELTS by @dkd-friedrich in #8
- [BUGFIX:BP:11.0] Fix expected variant results by @dkd-friedrich in #8
- [TASK] Update version matrix by @dkd-friedrich in #8



Release 11.0.7 - Last non ELTS release
======================================

TYPO3 9 LTS reached the ELTS stage: free community support for TYPO3 9 LTS ended on 30 sept. 2021.
We'll join the TYPO3s ELTS regiment and provide EXT:solr support for TYPO3 9 ELTS upwardly via our EB program.
Therefore the EXT:solr release-11.0.x will not be maintained in TYPO3-Solr/ext-solr repository any more. The maintenance and builds will be moved to other place.
The new EXT:solr 11.0.8+ for TYPO3 9 ELTS versions will be provided via dkds EB program.


Release 11.0.6
==============

This is a bugfix-only release that contains only bugfixes

This is a bugfix-only release that contains:

- [BUGFIX:BP:11-0] Respect TCA setting of 'tstamp' field (#3037)
- [BUGFIX:BP:11.0] Update SolrNotAvailable.html (#3020)
- [BUGFIX] Recursive constants (#3048)
- [BUGFIX] Follow up to recursive constants (#3058)
- [BUGFIX:BP:11.0] Don't use jQuery.ajaxSetup (#2503)
- [TASK:11.0] Replace mirrors for Apache Solr binaries on install-solr.sh (#3094)


Release 11.0.5
==============

This is a bugfix-only release that contains only bugfixes


- [TASK] 2021.12.13 Rebuild Docker images due of(CVE-2021-44228)
- [BUGFIX] Delete documents for valid connections only b99d7ad (#2940)
- [TASK] Make Apache Solr v8.6+ compatible 12b9483 (#2938)
- [TASK] Bump Chart.js to v2.9.4 4eacf89 (#2946)
- [BUGFIX] use pages configuration as default #issue-2742 d05f77e (#2742)
- [BUGFIX] ENV vars not handled correctly in site management module d83c7d1 (#2576)
- [BUGFIX] Delete synonyms with URL special chars 5905fdb (#2336)
- [BUGFIX] Fix typo in CoreOptimizationModule/Index.html 3163d25 (#2965)
- [BUGFIX] Use correct html tags in templates 31e2d2c (#2970)
- [BUGFIX] Add missing applicationType to faked request 19baedc (#2932)
- [TASK] Add language cache to SiteUtility 6f7e4d1 (#2908)
- [BUGFIX] writing errorHandling of site configuration 1ff6ca3 (#2913)
- [TASK] Make language cache work with multi site setups b7a39c1 (#2986)
- [BUGFIX] set base uri to face frontend request 2c34ae9 (#2914)
- [BUGFIX] getRangeString(): check type before format() - call a99275a (#2942)
- [BUGFIX] Fix type error in UrlHelper 17f1653 (#2756)


Release 11.0.4
--------------

This is a bugfix-only release that contains

- [BUGFIX] Removes secondary parameter c6a9dcc (#2746)
- [DOCS] Use \*_PORT variable for setting the port fca6f68 (#2759)
- [BUGFIX] Correct Content-Type header for suggest response e843b44 (#2783)
- [TASK] Change configuration files to TYPO3 file extensions 6d513e6 (#2813)
- [BUGFIX] garbage collector on translations 9adcc40 (#2797)
- [BUGFIX] Quote field within score calculation 3969340 (#2824)
- [BUGFIX] disabled Solr Sites e7bc3ab (#2795)
- [BUGFIX] Enable unicode when fetching pages eb33376 (#2810)
- [TASK] Disable cache time information for ajax request f54213f (#2834)
- [TASK] Adjust composer TYPO3 version constrains for EXT:Solr 11.0.4+ 50df86a (#2844)
- [FEATURE] Allow stdWrap on sorting label 5f2cee2 (#2339)
- [BUGFIX] Fix handling of case sensitive variant ids (#2865)
- [FEATURE] Store number of existing variants 9c88401 (#2870)
- [BUGFIX] Function call with non existing variable 0a69d45 (#2842 / #2520)
- [BUGFIX:BACKPORT:11] Fix missing variant field value 8e0c648 (#2878)
- [BUGFIX] Exception on Cached state of TranslateViewHelper 1765751 (#2830)
- [BUGFIX] Check if $recordUid is non-numeric before substitution a9cf555 (#2836)
- [TASK] Remove usages of Prophet by all occurrences within TYPO3 API 3bbf25a, 45b1703, 4f2b37a (#2862)
- [TASK] Remove TYPO3 long time ago deprecated cache class 79cafe9 (#2782)
- [BUGFIX] Change filter for workspace 5408889 (#2847)
- [BUGFIX] Use Iconfactory to retrieve record icons fa77962 (#2900)
- [BUGFIX] Language overlay for records is not retrieved since solr Version 11.x (#2788)
- [BUGFIX] Temporary free mode fix d5e936b
- [BUGFIX] Content id in language aspect c84ce1b
- [BUGFIX] Language aspect for indexer 9af09f3
- [BUGFIX] remove escaping on suggestion prefix f70829e (#2917)
- [FEATURE] Exclude sub entries of page/storage recursively 4151a25 (#2934)
- [BUGFIX] Make relevance sorting option markable as active bc813c8 (#2922)


Release 11.0.3
==============

This is a bugfix-only release that contains only a few bugfixes

- [TASK] Use minor version of solr docker image (#2740)
- [BUGFIX] Make sure HtmlContentExtractor::cleanContent() is UTF-8 safe (#2514)
- [BUGFIX] Database exception in RecordMonitor for records no-"enable" columns (#2512)
- [BUGFIX] Indexing of records fails with solr 10.x (#2521)
- [BUGFIX] Hard codes plugin namespace (#2732)
- [BUGFIX] Restricted pages are not being indexed in Typo3 10 (#2695)
- [BUGFIX] Prevent duplicate urls for page 0 (#2718)
- [BUGFIX] Fix assignment for page uid variable (#2664)
- [BUGFIX] Use num_found in static db table (#2668)
- [BUGFIX] Build core base path right, when path is slash only (#2692)
- [BUGFIX] Fix missing renderType attribute in flexform for search plugin (#2669)
- [BUGFIX] Add option to override 'port' in frontend indexing URL (#2618)
- [BUGFIX] Reset uriBuilder before building a new uri (#2658)
- [DOCS] Multiple improvements to the docs


Release 11.0.2
==============

This is a bugfix-only release that contains only a few bugfixes

- [TASK] Add warning in the docs that a fqdn is required for the sitehandling
- [BUGFIX] Re-enable Integration Tests for TYPO3 v10
- [BUGFIX] Fix unit tests with new controller context check
- [BUGFIX] Fix tests and add groups for tests
- [BUGFIX] Remove mocks in TYPO3 v10 Integration tests
- [BUGFIX] Remove unneeded constant
- [BUGFIX] Fix travis.yml to use correct stable versions
- [BUGFIX] Ensure to hand in PSR-7 Request to TSFE->getPageAndRootlineWithDomain
- [BUGFIX] Remove unneeded is_siteroot flag in nested storage folder
- [BUGFIX] Always return array on non-mounted sites
- [BUGFIX] Fix multiple rootpages in nested sites
- [BUGFIX] Prevent SiteNotFoundException in reports module


Release 11.0.1
==============

This is a bugfix-only release that contains only a few bugfixes

- [BUGFIX] Fix documentation and Versionmatrix
- [BUGFIX] Fix failing build on docker hub


Release 11.0.0
==============

We are happy to release EXT:solr 11.0.0.
The focus of this release was the support of TYPO3 10 LTS.

**Important**: This version is installable with TYPO3 9 and 10 LTS. For TYPO3 9 LTS at least version 9.5.16 is required.
EXT:solr 11 requires the usage of the TYPO3 site handling for the configuration of solr.

The ```legacyMode``` that allows the usage of domain records and configuration of Solr cores in TypoScript was dropped with EXT:solr 11.


New in this release
-------------------

With EXT:solr 11 we provide the support of TYPO3 10 LTS. If you update to EXT:solr 11, make sure, that you are using the TYPO3 site management to manage your Apache Solr endpoints.
Thanks to: Achim Fritz & b13 for the support on that topic


Support of Apache Solr 8.5.1
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

With EXT:solr 11 we support Apache Solr 8.5.1, the latest release of Apache Solr.

To see what was changed in Apache Solr 8.5.x please read the `release notes of Apache Solr <https://archive.apache.org/dist/lucene/solr/8.5.1/changes/Changes.html>`_


Small improvements and bugfixes
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Beside the major changes we did several small improvements and bugfixes:

* Enable SuggestAction to Provide pure JSON https://github.com/TYPO3-Solr/ext-solr/pull/2544 (Thanks to Julian Strecker)
* Update PHP class docblock https://github.com/TYPO3-Solr/ext-solr/pull/2543 (Thanks to Jens Jacobsen)
* Add typecasting https://github.com/TYPO3-Solr/ext-solr/pull/2487 (Thanks to dev-rke)
* Fix misinterpreted environment variables https://github.com/TYPO3-Solr/ext-solr/pull/2550 (Thanks to in2code and Markus Friedrich)
* IndexInspector is showing wrong core to document relation https://github.com/TYPO3-Solr/ext-solr/issues/2553 (Thanks to Timo Hund)
* Implode deprecation for PHP 7.4  https://github.com/TYPO3-Solr/ext-solr/pull/2558 (Thanks to Goddart Gothe)
* Place autocomplete div inside form https://github.com/TYPO3-Solr/ext-solr/issues/2569 (Thanks to Koen Wouters)
* Docker image managed resources are not writable https://github.com/TYPO3-Solr/ext-solr/pull/2583 (Thanks to Rafael Kähm)
* Fix indexing when default language is disabled in site config https://github.com/TYPO3-Solr/ext-solr/pull/2596 (Thanks to Patrick Daxböck, Hannes Lau, Kai Lochbaum & Timo Hund)
* Use object manager in all places of facet creation https://github.com/TYPO3-Solr/ext-solr/pull/2532 (Thanks to Sascha Nowak)
* Allow Wildcards in Filter Queries https://github.com/TYPO3-Solr/ext-solr/pull/2535 (Thanks to Philipp Parzer)
* Add FAQ for different host/port configuration https://github.com/TYPO3-Solr/ext-solr/pull/2509 (Thanks to Florian Langer)
* Replace current URL with new filter URL https://github.com/TYPO3-Solr/ext-solr/pull/2557 (Thanks to Klaus Hörmann-Engl)
* Add colon after user if password given https://github.com/TYPO3-Solr/ext-solr/pull/2537 (Thanks to Thomas Löffler)
* Remove eval int from port in site configuration  https://github.com/TYPO3-Solr/ext-solr/pull/2599 (Thanks to Georg Ringer)
* Replace usage of TYPO3_branch https://github.com/TYPO3-Solr/ext-solr/pull/2600 (Thanks to Georg Ringer)
* Remove langdisable=1 in FlexForms https://github.com/TYPO3-Solr/ext-solr/pull/2601 (Thanks to Georg Ringer)

Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* dev-rke
* Florian Langer
* Georg Ringer
* Goddart Goth
* Hannes Lau
* Jens Jacobsen
* Kai Lochbaum
* Klaus Hörmann-Engl
* Koen Wouters
* Markus Friedrich
* Markus Schwemer
* Patrick Daxböck
* Philipp Parzer
* Rafael Kähm
* Sascha Nowak
* Thomas Löffler
* Timo Hund

Also a big thanks to our partners that have joined the EB2020 program:

* +Pluswerk AG
* .hausformat GmbH
* 3m5. Media GmbH
* 4eyes GmbH
* Agora Energiewende Smart Energy for Europe Platform (SEFEP) gGmbH
* Amedick & Sommer Neue Medien GmbH
* AUSY SA
* b13 GmbH
* BARDEHLE PAGENBERG Partnerschaft mbB
* BIBUS AG Group
* Bitmotion GmbH
* brandung GmbH & Co. KG
* cab services ag
* clickstorm GmbH
* comwrap GmbH
* cron IT GmbH
* CS2 AG
* cyperfection GmbH
* digit.ly GmbH
* Digitale Offensive GmbH Internetagentur
* E-Magineurs
* Eidg. Forschungsanstalt WSL
* FGTCLB GmbH
* FTI Touristik GmbH
* GAYA - Manufacture digitale
* Hochschule für Polizei und öffentliche Verwaltung Nordrhein-Westfalen
* hotbytes GmbH & Co. KG
* IHK Neubrandenburg
* in2code GmbH
* Inotec Sicherheitstechnik GmbH
* jweiland.net
* Kassenzahnärztliche Vereinigung Bayerns (KZVB)
* Kassenärztliche Vereinigung Rheinland-Pfalz
* Landeskriminalamt Thüringen
* LfdA – Labor für digitale Angelegenheiten GmbH
* Macaw Germany Cologne GmbH
* Marketing Factory Consulting GmbH
* Masterflex SE
* mehrwert intermediale kommunikation GmbH
* mm Online Service
* netlogix GmbH & Co. KG
* Open New Media GmbH
* plan.net - agence conseil en stratégies digitales
* plan2net GmbH
* PROFILE MEDIA GmbH
* ressourcenmangel dresden GmbH
* RKW Rationalisierungs- und Innovationszentrum der Deutschen Wirtschaft e. V.
* ruhmesmeile GmbH
* Sandstein Neue Medien GmbH
* Stadt Wien - Wiener Wohnen Kundenservice GmbH
* Stefan Galinski Internetdienstleistungen
* TOUMORØ
* Typoheads GmbH
* unternehmen online GmbH & Co. KG
* VisionConnect GmbH
* werkraum Digitalmanufaktur GmbH
* WIND Internet
* zimmer7 GmbH


How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on `GitHub <https://github.com/TYPO3-Solr/ext-solr>`__
* Ask or help or answer questions in our `Slack channel <https://typo3.slack.com/messages/ext-solr/>`__
* Provide patches through Pull Request or review and comment on existing `Pull Requests <https://github.com/TYPO3-Solr/ext-solr/pulls>`__
* Go to `www.typo3-solr.com <https://www.typo3-solr.com>`__ or call `dkd <http://www.dkd.de>`__ to sponsor the ongoing development of Apache Solr for TYPO3

Support us by becoming an EB partner:

http://www.typo3-solr.com/en/contact/

or call:

+49 (0)69 - 2475218 0


