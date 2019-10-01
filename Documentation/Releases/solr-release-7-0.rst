.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _releases-7:

===========================
Apache Solr for TYPO3 7.0.0
===========================

We are happy to release EXT:solr 7.0.0. This release brings several smaller and some bigger changes

New in this release
===================


FLUID Templating
----------------

One and a half years ago we started to implement FLUID templating for EXT:solr. This project was initially started as the addon solrfluid. Solrfuid was only available for our partners.

With EXT:solr 7.0.0 the new templating is the default templating in EXT:solr. A lot of code was added and several old stuff was removed. Since some things are conceptional different in FLUID and you also have a lot of possibilities we also dropped some parts, that can be build with fluid itself or do not make sence to do them before rendering the result in the view.

Most of the things just work like before but in the following parts we made conceptional changes (for good reasons):

* No css or javascript will be added to the page automatically with the page renderer! Because the integrator wants to have control on that and TYPO3 allows to add this with TypoScript we propose to add these things via typoscript. EXT:solr offers a lot of example typoscript templates e.g. to add the default css or to add the javascript for a range facet.

The following typoscript settings have been removed because they can be implemented with FLUID:

**plugin.tx_solr.search.faceting.facetLinkATagParams**

You can add them in your project partials. If you need it just for one facet, please overwrite the render partial with facet.partialName and render the attributes different there

**plugin.tx_solr.search.faceting.[facetName].facetLinkATagParams**

You can add them in your project partials. If you need it just for one facet, please overwrite the render partial with facet.partialName and render the attributes different there

**plugin.tx_solr.search.faceting.facets.[facetName].selectingSelectedFacetOptionRemovesFilter**

This can be implemented with FLUID logic. Please check the example "Search - (Example) Options with on/off toggle" that implements that (by using the partial Facets/OptionsToggle.html)

**plugin.tx_solr.search.results.fieldRenderingInstructions**

Please use custom ViewHelpers or the cObject ViewHelper for that.

**plugin.tx_solr.search.results.fieldProcessingInstructions**

Please use custom ViewHelpers or the cObject ViewHelper for that.

**Important:** The support of fluid templating would not have been possible without the financial support of all partners! If you want to support us with the implementation of features like this, please think about to join the EB 2017 or 2018. Special thanks also to Frans Saris and beech.it for working on solrfluid together!

* https://github.com/TYPO3-Solr/ext-solr/pull/1308

Backend Modules Restructured
----------------------------

In EXT:solr 7.0.0 the backend modules are structured into multiple backend modules. This makes the user experience in the TYPO3 backend more consistent and allows you, to give different permissions on each module.

When you login into the backend, you now have the following modules available:

* Info: Gives information of your Solr system, index fields and search usage.
* Core Optimization: This module can be used to maintain the synonyms and stopwords in the Apache Solr server.
* Index Queue: Gives an overview on indexed records and can be used to requeue records for indexing.
* Index Administration: This module can be used for administrator tasks on your solr system (clear index, index queue or reload a core)

* https://github.com/TYPO3-Solr/ext-solr/pull/1300


Add excludeValues for Facets
----------------------------

If you want to exclude an optionValue from the facets when they get retrieved, you can configure this now:

.. code-block:: typoscript

   plugin.tx_solr.search.faceting.facets.colors_stringM.excludeValues = red


The example below will exclude the option "red" from the results when it is in the response.

* https://github.com/TYPO3-Solr/ext-solr/pull/1364

Allow to configure custom entry Template
----------------------------------------

In previous EXT:solr versions it was possible to set a custom entry templating using:

.. code-block:: typoscript

   plugin.tx_solr.templateFiles.search = EXT:solr/Resources/Templates/PiSearch/search.htm


This configuration could be overwritten with a text value in the flexform.

With the move to FLUID we improved this part and made it more editor friendly:

* Since the view related settings are located in the <view> section we've move the template configuration there as well.
* You can now set a Templatename only (e.g. MySearch) to benefit from FLUID fallbacks (while setting a full path is still supported.
* You can configure availableTemplates that can be selected by the editor in the flexform.

The following example shows, how you can load your own partials and provide different entry templates for the editor:

.. code-block:: typoscript

   plugin.tx_solr {
       view {
           templateRootPaths.100 = EXT:your_config_extension/Resources/Private/Templates/
           partialRootPaths.100 = EXT:your_config_extension/Resources/Private/Partials/
           layoutRootPaths.100 = EXT:your_config_extension/Resources/Private/Layouts/
           templateFiles {
               results = Results
               results.availableTemplates {
                   default {
                       label = Default Searchresults Template
                       file = Results
                   }
                   products {
                       label = Products Template
                       file = ProductResults
                   }
               }
           }
       }
   }


With the prevision configuration the editor can switch from "Default Searchresults Template" to "Products Template".

* https://github.com/TYPO3-Solr/ext-solr/pull/1325
* https://github.com/TYPO3-Solr/ext-solr/pull/1483


Refactoring of Query API
------------------------

The Query class is one of the biggest classes in EXT:solr and grown over time. This class has now been splitted into several classes.
Along with that a concept of "ParameterBuilder" has been introduced. A ParameterBuilder is responsible to build a parameter part of the query.
E.g. the Grouping ParameterBuilder is responsible to build all parameters of the solr query for the grouping.

* https://github.com/TYPO3-Solr/ext-solr/pull/1385

Move FilterEncoder and FacetBuilder to Facet Package
----------------------------------------------------

In Solrfluid there was one folder for each facet, that contains the facet class and a parser that parsers the solr response into the facet object.
The opposite part(parse the url, build the solr query) was previously done in EXT:solr, with a FilterEncoder that was registered in the FacetRendererFactory.

Now because solrfluid and solr have been merged, this logic can also be streamlined. Every facet is now structured in a FacetPackage.

A FacetPackage describes:

* Which parser should be used to parse the solr response
* Which url decoder should be used to parse the EXT:solr query data
* Which query builder should be used to build the faceting query part

You can also implement custom facet types by registering an on FacetPackage with the FacetRegistry.

**Migration**:

When you have implemented an own FacetParser for solrfluid, you should add a FacetPackage, that references a UrlDecoder and QueryBuilder.
If you have used a custom FacetParser without registring a custom facet type in EXT:solr (ApacheSolrForTypo3\Solr\Facet\FacetRendererFactory::registerFacetType) you can just reference DefaultUrlDecorder and DefaultFacerQueryBuilder in your FacetPackage.

* https://github.com/TYPO3-Solr/ext-solr/pull/1319

Custom plugin namespace - Multiple Instances
--------------------------------------------

Before solrfluid was merged there were several parts in EXT:solr where the data was read using GeneralUtility::_GET. The drawback of this approach is that the structure of the urls is hard to change and it is not possible to have custom namespaces for each instance of a plugin.

With solrfuid a SearchRequest object was introduced. This object holds all data from the user request. Now this object is used, whenever data from the user action is read. This allows us to make the request namespace changeable. You can now add your custom plugin namespace to a search plugin instance.

* https://github.com/TYPO3-Solr/ext-solr/pull/1379

Doctrine Migration
------------------

As an ongoing task, we started with the migration of database queries to doctrine. Since the database is used in many parts of the extension there are still many parts open.
If you want to work on that, your help is very welcome.

* https://github.com/TYPO3-Solr/ext-solr/pull/1259
* https://github.com/TYPO3-Solr/ext-solr/pull/1265
* https://github.com/TYPO3-Solr/ext-solr/pull/1270
* https://github.com/TYPO3-Solr/ext-solr/pull/1271

Add --rootpageid to CLI command
-------------------------------

If you want or need to limit the initialization of solr connections to a special rootpage, you can now do this by adding the argument --rootpageid.

* https://github.com/TYPO3-Solr/ext-solr/pull/1305

Respect Setting includeInAvailableFacets and includeInUsedFacets
----------------------------------------------------------------

This setting was not evaluated in EXT:solrfluid before and is now available also with FLUID rendering.

* https://github.com/TYPO3-Solr/ext-solr/pull/1340

Respect requirements facet setting with fluid
---------------------------------------------

This setting was not evaluated in EXT:solrfluid before and is now available also with the FLUID rendering.

* https://github.com/TYPO3-Solr/ext-solr/pull/1401

Respect setting searchUsingSpellCheckerSuggestion with fluid
------------------------------------------------------------

This setting was not evaluated in EXT:solrfluid before and is now available also with the FLUID rendering.

* https://github.com/TYPO3-Solr/ext-solr/pull/1501


Get rid of dependency to sys_domain record
------------------------------------------

By now EXT:solr had the dependency on an existing domain record. This can be a problem, when you domain is dynamic or
you need to be able to generate it.

Now you can configure a domain by the rootPageId in the TYPO3_CONF_VARS, the domain record is still used, when nothing is configured here.

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['sites'][###rootPageId###]['domains'] = ['mydomain.com'];

**Note:**

There might be an approach to support this in TYPO3 Version 9 by the core and we will adopt this then.

During the implementation of this the logic to retrieve the SiteHash and get the SolrConfiguration was moved to the SiteRepository,
this requires an update of the scheduler instances because the scheduler saves a serialized task. Please run the shipped migration to
update scheduler tasks created with 6.1.x.

* https://github.com/TYPO3-Solr/ext-solr/pull/1512

Preparations for TYPO3 9
------------------------

Several things that will be removed with 9 have been changed:

* https://github.com/TYPO3-Solr/ext-solr/pull/1443
* https://github.com/TYPO3-Solr/ext-solr/pull/1452
* https://github.com/TYPO3-Solr/ext-solr/pull/1462

Bugfixes
========

* Enable zero-configuration use of Docker image: https://github.com/TYPO3-Solr/ext-solr/issues/1278
* Remove unused use statement: https://github.com/TYPO3-Solr/ext-solr/pull/1292
* Indexing record outside siteroot throws exception: https://github.com/TYPO3-Solr/ext-solr/pull/1299
* Mounted pages from outside of the page tree lead to index queue errores: https://github.com/TYPO3-Solr/ext-solr/pull/1294
* Ignore workspace in RootPageResolver: https://github.com/TYPO3-Solr/ext-solr/pull/1298
* Preserve sort order when fetching related records: https://github.com/TYPO3-Solr/ext-solr/pull/1326
* Fix logging of error when devlog is enabled: https://github.com/TYPO3-Solr/ext-solr/pull/1341
* Tracking changes in record from other siteroot is not working as expected https://github.com/TYPO3-Solr/ext-solr/pull/1348
* Relation handler should handle pages overlays correctly https://github.com/TYPO3-Solr/ext-solr/pull/1400


Removed Code
============

The following code has been removed since it is not used anymore:

Classes:

* ScriptViewHelper
* StyleViewHelper
* AbstractSolrBackendViewHelper
* StringUtility

Methods:

* Util::camelize
* Util::camelCaseToLowerCaseUnderscored
* Util::underscoredToUpperCamelCase
* Util::pageExists

Deprecated Code
===============

Methods:

* Query::setQueryFieldsFromString use setQueryFields(QueryFields::fromString('foo')) with QueryFields instead, will be removed in 8.0
* Query::getQueryFieldsAsString use getQueryFields()->toString() now if needed, will be removed in 8.0
* Query::setQueryField use getQueryFields()->set() now, will be removed in 8.0
* Query::escape Use EscapeService::escape now, when needed
* Query::addReturnField use getReturnFields()->add() now, will be removed in 8.0
* Query::removeReturnField use getReturnFields()->remove() now, will be removed in 8.0
* Query::getFieldList use getReturnFields()->getValues() now, will be removed in 8.0
* Query::setFieldList use setReturnFields() now, will be removed in 8.0
* Query::escapeMarkers not needed anymore, use your own implementation when needed
* Query::setNumberOfGroups use getGrouping()->setNumberOfGroups() instead, will be removed in 8.0
* Query::getNumberOfGroups use getGrouping()->getNumberOfGroups() instead, will be removed in 8.0
* Query::addGroupField use getGrouping()->addField() instead, will be removed in 8.0
* Query::getGroupFields use getGrouping()->getFields() instead, will be removed in 8.0
* Query::addGroupSorting use getGrouping()->addSorting() instead, will be removed in 8.0
* Query::getGroupSortings use getGrouping()->getSortings() instead, will be removed in 8.0
* Query::addGroupQuery use getGrouping()->addQuery() instead, will be removed in 8.0
* Query::getGroupQueries use getGrouping()->getQueries() instead, will be removed in 8.0
* Query::setNumberOfResultsPerGroup use getGrouping()->setResultsPerGroup() instead, will be removed in 8.0
* Query::getNumberOfResultsPerGroup use getGrouping()->getResultsPerGroup() instead, will be removed in 8.0
* Query::setFacetFields use getFaceting()->setFields() instead, will be removed in 8.0
* Query::addFacetField use getFaceting()->addField() instead, will be removed in 8.0
* Query::removeFilter use getFilters()->removeByFieldName() instead, will be removed in 8.0
* Query::removeFilterByKey use getFilters()->removeByName() instead, will be removed in 8.0
* Query::removeFilterByValue use getFilters()->removeByValue() instead, will be removed in 8.0
* Query::addFilter use getFilters()->add() instead, will be removed in 8.0

Method Arguments:

* Query::setGrouping now expects the first argument to be a Grouping object, compatibility for the old argument (bool) will be dropped in 8.0
* Query::setHighlighting now expects the first argument to be a Highlighting object, compatibility for the old arguments (bool, int) will be dropped in 8.0
* Query::setFaceting now expects the first argument to be a Faceting object, compatibility for the old arguments (bool) will be dropped in 8.0

Hooks:

* $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchResponse'] has been marked as deprecated and will be dropped in 8.0 please use a SearchResultSetProcessor registered in $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch'] as replacement.
* $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['processSearchResponse'] has been marked as deprecated and will be dropped in 8.0 please use a SearchResultSetProcessor registered in $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch'] as replacement.

Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors for this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Andreas Lappe
* Felix Eckhofer
* Frans Saris
* Georg Ringer
* Helmut Hummel
* Jonas Ulrich
* Marco Bresch
* Markus Friedrich
* Michael Skrynski
* Rafael Kähm
* Rémy DANIEL
* Sascha Egerer
* Sebastian Hofer
* Timo Hund

Also a big thanks to our partners that have joined the EB2017 program:

* .hausformat
* AGENTUR FRONTAG AG
* amarantus - media design & conding Mario Drengner & Enrico Nemack GbR
* Amedick & Sommer Neue Medien GmbH
* Andrea Pausch
* Animate Agentur für interaktive Medien GmbH
* artig GmbH & Co. KG
* b:dreizehn GmbH
* BIBUS AG Group
* Bitmotion GmbH
* cab services ag
* Causal Sarl
* CHIARI GmbH
* Citkomm services GmbH
* clickstorm GmbH
* Connecta AG
* Creative360
* cron IT GmbH
* CYBERhouse Agentur für interaktive Kommukation GmbH
* cyperfection GmbH
* data-graphis GmbH
* Deutsche Welthungerhilfe e.V.
* Deutscher Ärzteverlag
* Deutscher Volkshochschul-Verband
* Die Medialen GmbH
* die_schnittsteller gmbh
* Dörfer engineering services
* E-Magineurs
* EYE Communications AG
* Fachhochschule für öffentliche Verwaltung NRW Zentralverwaltung Gelsenkirchen
* familie redlich AG
* Fork Unstable Media GmbH
* hauptsache.net GmbH
* Havas Düsseldorf GmbH
* Hirsch & Wölfl GmbH
* Hochschule Furtwangen - IMZ Online Services
* Hochschule Konstanz
* Institut der deutschen Wirtschaft Köln Medien GmbH
* iresults gmbh
* ITK Rheinland
* itl Institut für technische Literatur AG
* jweiland.net
* Kassenärztliche Vereinigung Rheinland-Pfalz
* Kerstin Nägler Web & Social Media Beratung
* Landesinstitut für Schule und Medien Berlin-Brandenburg
* Leibniz Universität IT Services
* Libéo
* Lime Flavour GbR
* LINGNER CONSULTING NEW MEDIA GMBH
* LOUIS INTERNET
* Maximilian Walter
* MEDIA:ESSENZ
* mehrwert intermediäre kommunikation GmbH
* Mercedes-AMG GmbH
* mlm media process management GmbH
* n@work Internet Informationssystems GmbH
* Netcreators
* netz-haut GmbH
* neuwerk interactive
* Nintendo of Europe GmbH
* Onedrop Solutions GmbH
* Open New Media GmbH
* Paints Multimedia GmbG
* pixelcreation GmbH
* plan2net
* Pluswerk AG
* polargold GmbH
* punkt.de GmbH
* Raiffeisen OnLine GmbH
* ruhmesmeile GmbH
* Rundfunk und Telekom Regulierung GmbH
* Schweizer Alpen-Club SAC
* sitegeist media solutions GmbH
* Star Finanz-Software Entwicklung und Vertriebs GmbH
* Stefan Galinski Internetdienstleistungen
* Stratis - Toulon
* Studio Mitte Digital Media GmbH
* Studio 9 GmbH
* Systime A/S
* SYZYGY Deutschland GmbH
* takomat Agentur GbR
* THE BRETTINGHAMS GmbH
* TOUMORO
* Triplesense Reply GmbH
* Typoheads GmbH
* unternehmen online GmbH & Co. KG
* Universität Bremen
* VERDURE Medienteam GmbH
* WACON Internet GmbH
* webedit AG
* Webstore GmbH
* Webtech AG
* wegewerk GmbH
* Wohnungsbau- und Verwaltungsgesellschaft mbH Greifswald
* XIMA MEDIA GmbH
* zdreicom GmbH
* zimmer7 GmbH

Thanks to everyone who helped in creating this release!

Outlook
=======

In the next release we want to focus on the user experience in the backend and in the frontend. As preparation we collected several tasks.

The goal of some of them (e.g. bootstrap templating, checkbox facets, filterable options partial) is to make more things possible out of the box and make the extension more user friendly:

https://github.com/TYPO3-Solr/ext-solr/issues?q=is%3Aissue+is%3Aopen+label%3AUX

If you have allready implemented one this (or something else), that you want to share or make available outofthebox feel free to contanct us!

How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us in 2017 by becoming an EB partner:

http://www.typo3-solr.com/en/contact/

or call:

+49 (0)69 - 2475218 0


