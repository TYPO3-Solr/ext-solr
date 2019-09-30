.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. .. _releases-8.1:

===========================
Apache Solr for TYPO3 8.1.0
===========================

We are happy to release EXT:solr 8.1.0. The focus of EXT:solr 8.1.0 was, to improve the API for the new EB addon's "solrconsole" and "solrdebugtools".

New in this release
===================

Groups should be respected in suggest as well
---------------------------------------------

When solrfluidgrouping is installed the groups are also respected for the top results in the suggest.

* https://github.com/TYPO3-Solr/ext-solr/pull/1882


Display plugin name in page module
----------------------------------

This patch extends the hook PageModuleSummary displaying information about the plugins flexform settings in the page module. Now the plugin name will be displayed and linked to the edit form, similar to the default behavior.

* https://github.com/TYPO3-Solr/ext-solr/pull/1897


TypoScriptService moved from Extbase to Core
--------------------------------------------

The TypoScriptService was moved from extbase to the core and we now use the service in the TYPO3 core.

* https://github.com/TYPO3-Solr/ext-solr/pull/1910


Improvements for solrconsole
----------------------------

The implementation of solrconsole required several API changes for the implementation of the commands. These changes have been added with the following pull requests:

* https://github.com/TYPO3-Solr/ext-solr/pull/1919
* https://github.com/TYPO3-Solr/ext-solr/pull/1921
* https://github.com/TYPO3-Solr/ext-solr/pull/1936

Use Apache Solr 6.6.3
---------------------

We've updated shipped Apache Solr version to 6.6.3

* https://github.com/TYPO3-Solr/ext-solr/pull/1938

Documentation improvements
--------------------------

Several issues have been fixed in the documentation to keep it up-to-date.

* https://github.com/TYPO3-Solr/ext-solr/pull/1935
* https://github.com/TYPO3-Solr/ext-solr/pull/1951
* https://github.com/TYPO3-Solr/ext-solr/pull/1961
* https://github.com/TYPO3-Solr/ext-solr/pull/2014

Add datetime fields for new configuration
-----------------------------------------

For the example index configuration for EXT:news, the datetime fields have been added.

* https://github.com/TYPO3-Solr/ext-solr/pull/1944


Anonymize ip addresses by default
---------------------------------

Since several versions it is possible to anonymize the ip-address in the statistics. This is now enabled by default.

* https://github.com/TYPO3-Solr/ext-solr/pull/1962

Remove setting search.spellchecking.wrap
----------------------------------------

This setting is not used anymore. It was replaced by a label in fluid in version 7.

* https://github.com/TYPO3-Solr/ext-solr/pull/1988


Allow configuring additional persistent arguments in the search
---------------------------------------------------------------

Now you can configure custom url arguments in the search that are transported from page to page.

Use the following setting to configure them:

.. code-block:: typoscript

    plugin.tx_solr.search.additionalPersistentArgumentNames = foo, bar


* https://github.com/TYPO3-Solr/ext-solr/pull/1985

Set mm.autoRelax to true by default
-----------------------------------

When terms get removed because they are stopwords this might have an unwanted impact when the mm condition is evaluated. Setting mm.autoRelax to true fixes this.

* https://github.com/TYPO3-Solr/ext-solr/pull/2009

See also:

* https://issues.apache.org/jira/browse/SOLR-3085

Dispatch signals in search controller actions
---------------------------------------------

This patch add's signals to the SearchController that allow passing custom arguments to the search view's.

* https://github.com/TYPO3-Solr/ext-solr/pull/1908

Optimizations on suggest
------------------------

With the suggest there where several issues:

* The type could not be passed before, now you can pass the type to the SearchFormViewHelper as "suggestPageType" argument.
* The filters have been passed as global url arguments "filter" as json encoded array, what was hard to debug and inconsistent to the other parameters. We changed the parameter to the name "additionalFilters" and each filter is passed as an array item.

**Impact**: If you just use the default ViewHelpers and templates you need to change nothing. When you add custom filters to the suggest you now need to pass each filter as an array item of the argument "additionalFilters"

* https://github.com/TYPO3-Solr/ext-solr/pull/2026


TYPO3 9 compatibility
---------------------

With 8.1.0 we do **not** officially support TYPO3 9. You can install it on TYPO3 9.3.99 for development and we tried to fix the most important issues during the development but there are already
a few known issues:

https://github.com/TYPO3-Solr/ext-solr/issues?q=is%3Aissue+is%3Aopen+label%3A9LTS

The following pull requests have already been merged in order to optimize the TYPO3 9 compatibility:

* https://github.com/TYPO3-Solr/ext-solr/pull/1954
* https://github.com/TYPO3-Solr/ext-solr/pull/2017
* https://github.com/TYPO3-Solr/ext-solr/pull/2020

Bugfixes
========

* https://github.com/TYPO3-Solr/ext-solr/pull/1876 sortBy is not applied
* https://github.com/TYPO3-Solr/ext-solr/pull/1875 Ensure AjaxController returns a response
* https://github.com/TYPO3-Solr/ext-solr/pull/1893 Default value for $resultsPerPage should be 10
* https://github.com/TYPO3-Solr/ext-solr/pull/1859 Add initializeTsfe to cacheId for configurationObjectCache
* https://github.com/TYPO3-Solr/ext-solr/pull/1895 Prevent CURLE_BAD_CONTENT_ENCODING
* https://github.com/TYPO3-Solr/ext-solr/pull/1904 SearchRequest::getHighestGroupPage should return 1 even when group was passed
* https://github.com/TYPO3-Solr/ext-solr/pull/1888 Show suggests box next to query input field
* https://github.com/TYPO3-Solr/ext-solr/pull/1907 Duplicate id attribute solr-pagination
* https://github.com/TYPO3-Solr/ext-solr/pull/1926 Use correct property to show searched keywords
* https://github.com/TYPO3-Solr/ext-solr/pull/1963 Change type of fileSize to long
* https://github.com/TYPO3-Solr/ext-solr/pull/1965 Broken HierarchyFacet by nesting level 10+
* https://github.com/TYPO3-Solr/ext-solr/pull/1981 Invalid argument $configurationName passed to Queue::updateItem
* https://github.com/TYPO3-Solr/ext-solr/pull/1992 Use andWhere
* https://github.com/TYPO3-Solr/ext-solr/pull/1995 Fixes warnings in the SearchRequest
* https://github.com/TYPO3-Solr/ext-solr/pull/2012 No score analysis shown
* https://github.com/TYPO3-Solr/ext-solr/pull/2024 Download of stopwords and synonyms not working

Deprecated Code
===============

The following methods have been marked as deprecated and will be removed in EXT:solr 9.0.0:

* SearchResultSetService::getHasSearched() please use SearchResultSet::getHasSearched along with that the global template variable "hasSearched" will be removed with 9.0.0 as well.
* Search::getHasSearched() please use SearchResultSet::getHasSearched instead along with that the global template variable "hasSearched" will be removed with 9.0.0 as well.
* Util::isLocalizedRecord() please use TCAService::isLocalizedRecord instead
* Queue::initialize() please use Queue::initializeBySiteAndIndexConfiguration instead
* Queue::initializeIndexingConfigurations() please use Queue::initializeBySiteAndIndexConfiguration instead
* SortingHelper::getSortFields() please use the SearchResultSet to get the parsed sorting
* SortingHelper::getSortOptions() please use the SearchResultSet to get the parsed sorting

Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Andreas Lappe
* Benni Mack
* Florian Schöppe
* Frans Saris
* Jens Jacobsen
* Marc Bastian Heinrichs
* Markus Friedrich
* Michael Telgkamp
* Olivier Dobberkau
* Rafael Kähm
* Rémy DANIEL
* Thomas Löffler
* Timo Hund
* Thomas Hohn

Also a big thanks to our partners that have joined the EB2018 program:

* 4eyes GmbH
* Albervanderveen
* Agentur Frontal AG
* AlrweNWR Internet BV
* Amedick & Sommer
* AUSY SA
* Bibus AG
* Bitmotion GmbH
* bgm Websolutions GmbH
* bplusd interactive GmbH
* Centre de gestion de la Fonction Publique Territoriale du Nord (Siège)
* Citkomm services GmbH
* Consulting Piezunka und Schamoni - Information Technologies GmbH
* Cobytes GmbH
* Cows Online GmbH
* creativ clicks GmbH
* DACHCOM.DIGITAL AG
* Deutsches Literaturarchiv Marbach
* food media Frank Wörner
* Fachhochschule für öffentliche Verwaltung NRW
* FTI Touristik GmbH
* GAYA - La Nouvelle Agence
* Hirsch & Wölfl GmbH
* Hochschule Furtwangen
* ijuice Agentur GmbH
* Image Transfer GmbH
* JUNGMUT Communications GmbH
* Kreis Coesfeld
* LINGNER CONSULTING NEW MEDIA GMBH
* LOUIS INTERNET GmbH
* L.N. Schaffrath DigitalMedien GmbH
* MEDIA::ESSENZ
* Mehr Demokratie e.V.
* mehrwert intermediale kommunikation GmbH
* Mercedes AMG GmbH
* Petz & Co
* pietzpluswild GmbH
* pixelcreation GmbH
* plan.net
* Pluswerk AG
* Pottkinder GmbH
* PROVITEX GmbH
* Publicis Pixelpark
* punkt.de GmbH
* PROFILE MEDIA GmbG
* Q3i GmbH & Co. KG
* ressourcenmangel an der panke GmbH
* Roza Sancken
* Site'nGo
* SIWA Online GmbH
* snowflake productions gmbh
* Studio B12 GmbH
* systime
* SYZYGY Deutschland GmbH
* Talleux & Zöllner GbR
* TOUMORO
* THE BRETTINGHAMS GmbH
* TWT Interactive GmbH
* T-Systems Multimedia Solutions GmbH
* Typoheads GmbH
* Q3i GmbH
* Ueberbit GmbH
* zdreicon GmbH
* zimmer7 GmbH

Special thanks to our premium EB 2018 partners:

* b13 http://www.b13.de/
* dkd http://www.dkd.de/
* Image Transfer GmbH https://www.image-transfer.de/
* jweiland.net http://www.jweiland.net/
* Sitegeist http://www.sitegeist.de/

Thanks to everyone who helped in creating this release!

Outlook
=======

In the next release, we want to focus on the move to solarium and the support of the latest Apache Solr version.

How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us in 2018 by becoming an EB partner:

http://www.typo3-solr.com/en/contact/

or call:

+49 (0)69 - 2475218 0


