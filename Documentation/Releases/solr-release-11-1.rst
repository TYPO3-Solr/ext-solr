.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _releases-11-1:

============================
Apache Solr for TYPO3 11.1.0
============================

We are happy to release EXT:solr 11.1.0.
The focus of this release has been on URL and SEO optimizations.

**Important**: This version is installable with TYPO3 10 LTS only and contains some breaking changes, see details below.

New in this release
===================

Route enhancers
---------------

Introduce the TYPO3s route enhancer functionality for facets.
This feature allows to mask facets inside the query string or as part of the path segment.

See:
* https://github.com/TYPO3-Solr/ext-solr/pull/2755
* https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/ApiOverview/Routing/AdvancedRoutingConfiguration.html
* https://docs.typo3.org/p/apache-solr-for-typo3/solr/11.1/en-us/Routing/Index.html

Associative keys for tx_solr[filter] facet URL parameters
---------------------------------------------------------

Introduce a new style how the facet array represented inside of the url with a combination of key and value in order to be able to keep a specific order.

This feature allows to change the url parameters from index based to associative keys for facets.
Using associative keys, the value of a facet will be replaced with 1. A value of 1 means, that the facet is active. A value of 0 means, the value is inactive.

Additionaly a new sort option for url parameters is available. The sort of parameters is mandatory for associative keys.

By default Solr behaves as before and will be changed in future releases.

See: https://github.com/TYPO3-Solr/ext-solr/pull/2705

ASCII and Scandinavian Folding Filter
-------------------------------------

To improve the search behaviour we introduce folding filters, e.g. allowing to skip accents in search terms. The following languages are
now using the ASCII folding filter:

* dutch
* english
* finish
* french
* german
* hungarian
* irish
* italian
* polish
* portuguese
* serbian (for fields that don't include the Serbian Normalization Filter)
* spanish
* turkish

For the Scandinavian languages, Norwegian, Swedish and Danish, a similiar approach is used, but we're using the more specialized Scandinavian Normalization
and Scandinavian Folding Filters.

Folding process usally takes place at a late stage, so your configurations shouldn't be affected. But for the Scandinavian languages the Scandinavian Normalization
Filter processes the terms earlier, so your protected words for the Snowball Porter Filter, e.g. danish/protwords.txt, might be affected, please be sure to use the
right spelling (see https://solr.apache.org/guide/8_8/language-analysis.html#scandinavian-normalization-filter).

See: https://github.com/TYPO3-Solr/ext-solr/pull/2963

cHash configuration
-------------------

EXT:solrs components like range facets can not be properly handled by cHash stack, because the amount of possible range-combinations is infinite, therefore they must be excluded from cHash calculation.

This change makes it possible to exclude all EXT:solr parameters from cache hash. To prevent misconfigurations, the new extension configuration setting "pluginNamespaces" was introduced, which is used in FlexForm and in
TYPO3_CONF_VARS/FE/cacheHash/excludedParameters. This setting makes it impossible to chose invalid/unhandled EXT:solr plugin namespace on FlexForm (Plugin -> Options -> Plugin Namespace)

Please follow the following migration instructions

Plugin namespaces:
Needed only if other as default (tx_solr) plugin namespace is used in instance. Add the used namespace[s] to $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr']['pluginNamespaces'] or via backend
"Settings" -> "Extension Configuration" -> "solr" -> "A list of white listed plugin namespaces"

Global q parameter:
Needed only if global "q" parameter without plugin namespace is used and wants to be included in cache hash calculation. Set the setting $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['solr']['pluginNamespaces'] = '1'
or enable it via backend "Settings" -> "Extension Configuration" -> "solr" -> "Include/Exclude global q parameter in/from cacheHash"

See:
* https://github.com/TYPO3-Solr/ext-solr/commit/7b0e77c2680d9dea7861f7bcd33abc1e8664f289
* https://github.com/TYPO3-Solr/ext-solr/pull/2972

Scheduler task to optimize solr cores
-------------------------------------

This task allows you to optimize the indexes of given cores for a site at a planned time.

See:
* https://github.com/TYPO3-Solr/ext-solr/issues/2649
* https://github.com/TYPO3-Solr/ext-solr/pull/2666
* https://docs.typo3.org/p/apache-solr-for-typo3/solr/11.1/en-us/Backend/Scheduler.html#optimizing-cores-of-a-site


Apache Solr 8.9.0 support
-------------------------

With EXT:solr 11.1 we support Apache Solr 8.9.0, the latest release of Apache Solr.

To see what has changed in Apache Solr please read the release notes of Apache Solr:
https://solr.apache.org/docs/8_8_2/changes/Changes.html

Map managed resources to core-name
----------------------------------

Makes it possible to manage resources per core instead of previously used per schema approach.
Now is it possible to maintain the stopwords and synonyms for each core/site separately,
and avoid mixing the synonyms between sites using same core.

See:
* https://github.com/TYPO3-Solr/ext-solr/issues/2635
* https://github.com/TYPO3-Solr/ext-solr/pull/2794
* https://github.com/TYPO3-Solr/ext-solr/commit/fde8a64be3de538339e1608fbe44a8160ab9f023

Update to Solarium 6
--------------------

Solarium is upgraded from version 4 to version 6, so due to changes in Solarium various classes and data types had to be adapted.

There are two major changes you have to consider while upgrading:

* TypoScript option plugin.tx_solr.solr.timeout is dropped, settings for HTTP client $GLOBALS['TYPO3_CONF_VARS']['HTTP'] are now taken into account
* Solr path mustn't be prepended with "/solr/", refer to the "Getting Started > Configure Extension" section in our manual

Drop TYPO3 9 compatibility
--------------------------

To simplify the development we've dropped the compatibility for TYPO3 9 LTS. If you need to use TYPO3 9 please use the 11.0.x branch.

Small improvements and bugfixes
-------------------------------

Beside the major changes we did several small improvements and bugfixes:

* [TASK] Update TypoScript condition to Expression Language .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2996
* [BUGFIX] Use correct information about results per page in pagination .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2516
* [BUGFIX] getRangeString(): check type before format() - call .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2942
* [BUGFIX] set base uri to face frontend request .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2915
* [TASK] Add language cache to SiteUtility .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2908
  * [TASK] Make language cache work with multi site setups .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2986
* [BUGFIX] Changes on sub-tree of mounted source pages are not recognized .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2925
* [BUGFIX] Record indexing doesn't work anymore if page queue is disabled .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2241
* [FEATURE] Add and improve translations .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2874
* [TASK] Add Danish dictionary compound word token filter .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2975
* [BUGFIX] Add missing applicationType to faked request .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2933
* [BUGFIX] Use correct html tags in templates .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2970
* [BUGFIX] Fix typo in CoreOptimizationModule/Index.html .. __: https://github.com/TYPO3-Solr/ext-solr/commit/eb39ca60d45203e02ba282bd74211d8f35ddaf26
* [BUGFIX] Delete synonyms with URL special chars .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2959/commits/0a6456d3221ab36d896f96a6018636682d60198f
* [BUGFIX] ENV vars not handled correctly in site management module .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2576
* [TASK] Use LowerCaseFilterFactory .. __: https://github.com/TYPO3-Solr/ext-solr/commit/7a6ae684515333636a7761eb7e67db98363e6f8b
* [TASK] Bump Chart.js to v2.9.4 .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2946
* [BUGFIX] Delete documents for valid connections only .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2939
* [BUGFIX] Make relevance sorting option markable as active .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2852
* [FEATURE] Exclude sub entries of page/storage recursively .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2432
* [BUGFIX] Pass FrontendUserAuthentication to TypoScriptFrontendController .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2761
* [BUGFIX] remove escaping on suggestion prefix .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2917
* [BUGFIX] Language aspect for indexer .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2841
  * [BUGFIX] Content id in language aspect
  * [BUGFIX] Temporary free mode fix
* [BUGFIX] Use Iconfactory to retrieve record icons .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2900
* [BUGFIX] Adapt extractByQuery for Tika 1.24 .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2897/commits/3dfe978926d703bb792e5b4aab68958b77f49d36
* [FEATURE] Store number of existing variants .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2870
  * [BUGFIX] Fix expected variant results .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2897/commits/be0913d3541c9979ef492f588fd8bcc4796de1b4
  * [BUGFIX] Fix missing variant field value .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2879
  * [BUGFIX] Fix handling of case sensitive variant ids .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2865
* [BUGFIX] Change filter for workspace .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2847
* [TASK] Remove TYPO3 long time ago deprecated cache class (#2884) .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2782
* [BUGFIX] Check if $recordUid is non-numeric before substitution .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2836
* [BUGFIX] Exception on Cached state of TranslateViewHelper .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2830
* [BUGFIX] Function call with non existing variable .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2842
* [FEATURE] Allow stdWrap on sorting label .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2339
* [TASK] Disable cache time information for ajax request .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2833
* [BUGFIX] using named parameter for empty string comparison .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2703
* [BUGFIX] removing backticks in addSelectLiteral .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2701
* [BUGFIX] Enable unicode when fetching pages .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2810
* [BUGFIX] Sites with no Solr Configuration should not be considered .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2795
* [BUGFIX] Quote field within score calculation .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2824
* [BUGFIX] garbage collector on translations .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2797
* [TASK] Refactor class UrlHelper .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2758
* [BUGFIX] Use rawurldecode on facets to handle .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2806
* [TASK] Change configuration files to TYPO3 file extensions .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2814
* [BUGFIX] Unset extendToSubPages & hidden doesn't requeue subpages .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2433
* [BUGFIX] Error by textTight on some values .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2180
* [DOCS] Fix TypoScript path for lastSearches in the docs .. __: https://github.com/TYPO3-Solr/ext-solr/commit/dddccfc6a27a6801b9eccf4b57c0c654e055b1df
* [TASK] Remove mentions on \Apache_Solr_Document .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2789
* [BUGFIX] Correct Content-Type header for suggest response .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2783
* [DOCS] Use *_PORT variable for setting the port .. __: https://github.com/TYPO3-Solr/ext-solr/commit/4d264f28ef5a288039ae860015ee29013d8fcb8a
* [BUGFIX] Deprecated second parameter for BackendUserAuthentication->modAccess is used  .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2746
* [TASK] Add .editorconfig .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2768
* [TASK] Make sure HtmlContentExtractor::cleanContent() is UTF-8 safe .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2513
* [BUGFIX] Fix #2511: database exception in RecordMonitor .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2511
* [BUGFIX] Indexing of records fails with solr 10.x+  .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2520
* [DOCS] Fix path of suggest in typoscript settings .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2678
* [DOCS] Fix links in docs and CONTRIBUTING.md .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2697
* [BUGFIX] Hard codes plugin namespace .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2691
* [BUGFIX] Restricted pages are not being indexed in Typo3 10 .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2634
* [DOCS] Note that config.index_enable is still needed .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2735
* [BUGFIX] Prevent duplicate urls for page 0 .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2718
* [BUGFIX] Fix assignment for page uid variable .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2663
* [BUGFIX] Build core base path right, when path is slash only  .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2680
* [BUGFIX] Record Monitoring .. __: https://github.com/TYPO3-Solr/ext-solr/commit/fb504489bd1090e21f777672da35248c0df18c6d
* [DOCS] Improvements for contributing to the documentation .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2690
* [TASK] Drop TYPO3 9.5 LTS support for future release 11.1.x
* [BUGFIX] use num_found in static db table .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2667
* [BUGFIX] Fix missing renderType attribute in flexform for search plugin .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2661
* [FEATURE] Add option to override 'port' in frontend indexing URL .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2327
* [BUGFIX] Set accurate center position for loading animation .. __: https://github.com/TYPO3-Solr/ext-solr/pull/2568
* [BUGFIX] Reset uriBuilder before building a new uri .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2656
* [BUGFIX] Prevent SiteNotFoundException in reports module .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2624
* [FEATURE] Change FileWriter configuration to use logFileInfix .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2626
* [BUGFIX] Ensure to hand in PSR-7 Request to TSFE->getPageAndRootlineWithDomain .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2640
* ... See older commits, which are a part of prevous releases: https://github.com/TYPO3-Solr/ext-solr/commits/master?after=ec72de7f14c16ee985ab26b5b6791518e348de96+139&branch=master


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Achim Fritz
* Benni Mack
* Christoph Lehmann
* Daniel Koether
* Daniel Siepmann
* Dmitry Dulepov
* @dev-rke
* @dsone
* @FearFreddy
* @Figilano
* @frommo
* Georg Ringer
* Jens Jacobsen
* Lars Tode
* @leslawp
* Marc Bastian Heinrichs
* Markus Friedrich
* Markus Kobligk
* Michiel Roos
* Peter, CyberForum e.V
* Philipp Kitzberger
* Rafael Kähm
* Ruud Silvrants
* Sascha Egerer
* Sebastian Hofer
* Sebastian Michaelsen
* Stefano Kowalke
* Stephan Brun
* Sybille Peters
* Timo Hund
* Tobias Kretschmann

Also a big thanks to our partners that have joined the EB2021 program:

* +Pluswerk AG
* 711media websolutions GmbH
* Abt Sportsline GmbH
* ACO Severin Ahlmann GmbH & Co. KG
* AVM Computersysteme Vertriebs GmbH
* cosmoblonde GmbH
* creativ clicks GmbH
* cron IT GmbH
* CS2 AG
* CW Media & Systems
* Earlybird GmbH & Co KG
* Earlybird GmbH & Co KG
* FLOWSITE GmbH
* form4 GmbH & Co. KG
* Getdesigned GmbH
* Granpasso Digital Strategy GmbH
* Ikanos GmbH
* internezzo ag
* Intersim AG
* Ion2s GmbH
* Leitgab Gernot
* mellowmessage GmbH
* Moselwal Digitalagentur UG (haftungsbeschränkt)
* network.publishing Möller-Westbunk GmbH
* OST Ostschweizer Fachhochschule
* Plan.Net Suisse AG
* Provitex GmbH
* punkt.de GmbH
* queo GmbH
* Rechnungshof
* Schoene neue kinder GmbH
* SIT GmbH
* SIZ GmbH
* Stämpfli AG
* Triplesense Reply Frankfurt
* TWT reality bytes GmbH
* visol digitale Dienstleistungen GmbH
* Web Commerce GmbH
* webconsulting business services gmbh
* webschuppen GmbH
* Webstobe GmbH
* Webtech AG
* wow! solution
* XIMA MEDIA GmbH
* Bundesanstalt Statistik Österreich
* ECOS TECHNOLOGY GMBH
* Fachhochschule Erfurt
* Hochschule Furtwangen - IMZ Online-Services
* Hochschule Niederrhein University of Applied Sciences
* l'Autorité des marchés financiers
* La Financière agricole du Québec
* LfdA - Labor für digitale Angelegenheiten GmbH

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


