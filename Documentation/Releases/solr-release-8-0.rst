.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _releases-8:

===========================
Apache Solr for TYPO3 8.0.0
===========================

We are happy to release EXT:solr 8.0.0. The focus of EXT:solr 8.0.0 was, to improve the user experience in the frontend and backend.

New in this release
===================

In the following paragraphs we want to summarize the new features that will be shipped with EXT:solr 8.0.0

New suggest
-----------

We've replaced the old jQuery UI based autosuggest with a new suggest (https://github.com/devbridge/jQuery-Autocomplete). The advanced suggest can not only show the suggestions, it can also show a configurable amount of top search results.

When the user clicks on the result, he can directly jump to the result page without opening the search results page.

Thanks:

* Frans Saris and http://www.beech.it for sharing the codebase of the initial patch!


Related pull request: https://github.com/TYPO3-Solr/ext-solr/pull/1638


JSON Faceting for options facets
--------------------------------

Apache Solr offers a JSON API for faceting since several versions. Starting with the options facet we've added the support to use this JSON faceting API in EXT:solr.

The support of the JSON API, in general, allows us to build new features on top of that API, that was impossible before. With the first implementation we've added the following features:

By now an option was simply the value and the count, that reflects the number of documents that belong to that option. At EXT:solr 8.0.0 we've added a TypoScript option that is called "metrics", that allows us to collect and show several metrics from documents that belong to a facet option. Examples of metrics are e.g "sum of downloads", "average price",... These metrics will be available in Option model in the FLUID template and can also be used to sort the facet options.

The following example shows an configured options facet with a configured metric:

.. code-block:: typoscript

   plugin.tx_solr.search.faceting.facets.type.metrics {
       newest = max(created)
       oldest = min(created)
   }

In the FLUID template you could use the following code in the facet partial to render those metrics:


.. code-block:: xml

   <span>
      newest: {option.metrics.newest -> f:format.date(format: 'Y-m-d H:i:s')}
   </span>
   <span>
      oldest: {option.metrics.oldest -> f:format.date(format: 'Y-m-d H:i:s')}
   </span>


Thanks:

* Thanks to Jens Jacobsen and UEBERBIT for sponsoring Jens work on that feature at our code sprint.

Since we'replaced the whole internal communication from EXT:solr to Apache Solr when options facets are used we are very happy to get your feedback and bug reports when you use the options facets with EXT:solr

Related pull request: https://github.com/TYPO3-Solr/ext-solr/pull/1764

Group facet options by prefix
-----------------------------

When you have option facets with a lot of options, it would be nice to group those options by a prefix. An example is that you group all options by the starting letter to organize them in tabs:

With EXT:solr 8 we ship the following components that allow grouping your facet options to arrange them as you need them in your template:

* LabelFilterViewHelper: Can be used to filter options based on a prefix of the label.
* LabelPrefixesViewHelper: Can be used to access all available prefixes of the facet options.
* TypoScript example template "(Example) Options grouped by prefix" that configures a grouped facet on the author field


Thanks: This feature was sponsored by https://www.linnearad.no/

Related pull request: https://github.com/TYPO3-Solr/ext-solr/pull/1717

Filterable options facet
------------------------

In the previous section, the facets get grouped by prefix to organize a large number of options. Another way that you also often see on the web is to allow to filter the options with an additional input box above the facet.

The implementation of that feature is possible just with a partial and a few JavaScript components. To simplify the integration of that feature in a project we ship

* Example FLUID partial that uses the filter for options
* Example JavaScript that implements the filter functionality
* Example TypoScript "Search - (Example) Options filterable by option value" that uses the partials and javascript for a facet

Related pull request: https://github.com/TYPO3-Solr/ext-solr/pull/1741


Default partials with bootstrap.css
-----------------------------------

The old templating was created with custom CSS that was shipped with the extension. Since we want to decrease the effort that is required to create a mobile search and many integrators use bootstrap.css we decided to ship bootstrap templates by default. If you want to use another framework or your own custom CSS you are still able to do that with custom templates.

Nevertheless, the mobile search in a TYPO3 introduction installation with bootstrap is much better than before and your effort to adopt it should be reduced.

Related pull request: https://github.com/TYPO3-Solr/ext-solr/pull/1738

Performance improvements
------------------------

In EXT:solr 7.x and below a ping request was done before each search. In EXT:solr 8.0.0 we just catch a failed search and handle the unavailability. This saves up to 30% time because we just need one HTTP request to Apache Solr instead of 2.

Related pull request: https://github.com/TYPO3-Solr/ext-solr/pull/1660

Improved index inspector
------------------------

In the previous versions, we've introduced own backend modules that can also be used by regular TYPO3 users to perform several tasks. With EXT:solr 8.0.0 the index inspector will be moved from the common info module to our info module:

Besides the move, we also added the functionality to ReQueue a single document from the index inspector when you have permissions on the index queue module.

Related pull request: https://github.com/TYPO3-Solr/ext-solr/pull/1763

Use TYPO3 Guzzle for page index requests
----------------------------------------

The indexing of pages is now done with the shipped Guzzle client in TYPO3.

Thanks: Thanks to Benni Mack from b13 who has implemented that feature http://www.b13.de/

Related pull request: https://github.com/TYPO3-Solr/ext-solr/pull/1837

SOLR_CLASSIFICATION cObject
---------------------------

When you index a lot of documents you might want to create facets based on patterns that occur in the content.

The cObject SOLR_CLASSIFICATION allows you to do a lightweight classification based on regex patterns that you configure in the index configuration.

The following example shows how SOLR_CLASSIFICATION can be used to map patterns on classes that are indexed into a Solr field that could be used for faceting:

.. code-block:: typoscript

   plugin.tx_solr.index.queue.pages.businessarea_stringM = SOLR_CLASSIFICATION
   plugin.tx_solr.index.queue.pages.businessarea_stringM {
      field = __solr_content
      classes {
         automotive {
            patterns = car,jeep,SUV
            class = automotive
         }
         pharma {
            patterns = pharma,doc,medicine
            class = pharma
         }
      }
   }

With the configuration above Solr documents get the value "automotive" assigned in the Solr field "businessarea_stringM" when the content contains the term "car", "jeep" or "SUV".

Thanks: Thanks to http://www.bibus.ch who sponsored the implementation of this feature.

Related pull request: https://github.com/TYPO3-Solr/ext-solr/pull/1723

Phrase support (phrase, bigram, trigram)
----------------------------------------

With plugin.tx_solr.search.query.(phrase/bigramPhrase/trigramPhrase).fields you can control what is passed to Solr with the ps,ps2 and ps3 value.

With these phrase fields, you can boost documents where phrases occur in close proximity. This can be very handy when you want to tune your search in terms of relevancy.

Related links:

* https://lucene.apache.org/solr/guide/66/the-dismax-query-parser.html#TheDisMaxQueryParser-ThepfPhraseFields_Parameter

Related pull request: https://github.com/TYPO3-Solr/ext-solr/pull/1735

Tie parameter support
---------------------

With plugin.tx_solr.search.query.tieParameter you can now configure the tie value that is passed to Apache Solr.

This value allows you to configure the impact of low scoring fields to the overall score. 0.0 means, that only high score fields will matter, 0.99 means that all fields have the same impact

Related links:

* https://solr.pl/en/2012/02/06/what-can-we-use-dismax-tie-parameter-for/
* https://lucene.apache.org/solr/guide/66/the-dismax-query-parser.html#TheDisMaxQueryParser-ThetieTieBreaker_Parameter


Thanks: Thanks to Marcus Schwemer and in2code that sponsored and shared that feature.

Related pull request: https://github.com/TYPO3-Solr/ext-solr/pull/1690

Doctrine ready
--------------

TYPO3 8 introduced Doctrine DBAL for database queries and the old API will be removed in TYPO3 9. Since we've used a lot of repositories with custom SQL queries, we had to rewrite a lot of queries.

In EXT:solr we've used the chance to restructure the SQL related code and move them to repositories whenever this was possible.

With EXT:solr 8 every usage of the old database API is removed and we are prepared in that way to be ready for TYPO3 9.

Fluent API for Queries with the QueryBuilder
--------------------------------------------

Many parts of the code of EXT:solr deal with queries for Apache Solr that's no surprise :). The corresponding parts in the code especially the Query class had grown over time and reached a huge complexity.

This has several drawbacks:

* It is hard to integrate new features (e.g the tiebreaker or bigram features)
* TYPO3 specific logic and common Apache Solr logic is mixed and makes it hard to switch to frameworks like e.g. Solarium
* The Query class does multiple things: Build the query, initialize the query from the configuration,... This could be split into multiple components.

To get better in that regards our goal is to split the Query into:

* Query: Aggregate that is responsible to build the Solr query string based on the options
* QueryBuilder: Builder class that is responsible to build an initialized Query object e.g. based on TypoScript configuration and user input.

With the current state the QueryBuilder does the following to build a Query from the user input:

.. code-block:: php

   $query = $queryBuilder->newSearchQuery($rawQuery)
    ->useResultsPerPage($resultsPerPage)
    ->useReturnFieldsFromTypoScript()
    ->useQueryFieldsFromTypoScript()
    ->useInitialQueryFromTypoScript()
    ->useFiltersFromTypoScript()
    ->useFacetingFromTypoScript()
    ->useVariantsFromTypoScript()
    ->useGroupingFromTypoScript()
    ->useHighlightingFromTypoScript()
    ->usePhraseFieldsFromTypoScript()
    ->useBigramPhraseFieldsFromTypoScript()
    ->useTrigramPhraseFieldsFromTypoScript()
    ->getQuery();

Finally, this allows us to:

* Integrate new features faster
* Allow devs to compose own queries that use or ignore several aspects of EXT:solr
* Simplify the switch or integration of a generic Solr API that is independent of TYPO3 (e.g. Solarium)


On the way to TYPO3 9
---------------------

With EXT:solr 8.0.0 we will not officially support TYPO3 9 since it is not an LTS release! Nevertheless, we want to stay close to the TYPO3 core and allow the usage in 9 already.

By now we mainly fix Doctrine and Composer related issues and support the dropped "pageslanguageoverlay" table.

So to sum up... EXT:solr 8.0.0 will mainly support TYPO3 8 LTS and we will support TYPO3 9.x a good as we can without losing the backward compatibility to TYPO3 8 LTS.

Bugfixes
========

* Can not set the facet sorting to count when global sorting is set to index: https://github.com/TYPO3-Solr/ext-solr/pull/1667
* Filter with Flexform in backend does not work when value contains whitespaces: https://github.com/TYPO3-Solr/ext-solr/issues/1742
* SOLR_RELATION does not recognize sys_categories for translated pages: https://github.com/TYPO3-Solr/ext-solr/issues/1812
* Allow to use EXT:solr with sql strict mode: https://github.com/TYPO3-Solr/ext-solr/issues/1785
* Missing array keys in facet options after manual sorting: https://github.com/TYPO3-Solr/ext-solr/pull/1712
* partialName is missing in TypoScript reference:  https://github.com/TYPO3-Solr/ext-solr/pull/1730

Removed Code
============

Query Refactoring
-----------------

In the long run we want to be able to use other PHP frameworks for Apache Solr e.g. solarium(http://www.solarium-project.org/). To make this possible, we
need to split the pure Solr query related logic from the TYPO3Solr specific query logic (e.g. accessFilter,...). To get a step closer into this direction, we've extracted
the logic that is required to build a TYPO3 specific Solr query into the QueryBuilder. The pure Solr related query logic remains in the Query class.

Impact:

* Whenever you create or modify queries you should use the QueryBuilder class for that. In one of the next releases we will support to create solarium queries with this QueryBuilder.

Beside the query refactoring, that required to remove and change several methods, the following code has been removed:

Hooks:

* $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchResponse'] has been marked as deprecated and will be dropped in 8.0 please use a SearchResultSetProcessor registered in $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch'] as replacement.
* $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['processSearchResponse'] has been marked as deprecated and will be dropped in 8.0 please use a SearchResultSetProcessor registered in $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['afterSearch'] as replacement.

Deprecated Code
===============

The following methods have been marked as deprecated and will be removed in EXT:solr 9.0.0

* ApacheSolrForTypo3\Solr\Search::getResultDocumentsRaw - Use the SearchResultsSet::getSearchResults now
* ApacheSolrForTypo3\Solr\Search::getResultDocumentsEscaped - Use the SearchResultsSet::getSearchResults now
* ApacheSolrForTypo3\Solr\Search::getFacetCounts - Use the SearchResultSet::getFacets now
* ApacheSolrForTypo3\Solr\Search::getFacetFieldOptions - Use the SearchResultSet::getFacets now
* ApacheSolrForTypo3\Solr\Search::getFacetQueryOptions - Use the SearchResultSet::getFacets now
* ApacheSolrForTypo3\Solr\Search::getFacetRangeOptions - Use the SearchResultSet::getFacets now
* ApacheSolrForTypo3\Solr\Search::getSpellcheckingSuggestions - Use SearchResultSet::getSpellcheckingSuggestions
* ApacheSolrForTypo3\Solr\Query is deprecated, use ApacheSolrForTypo3\Solr\Domain\Search\Query\Query now
* ApacheSolrForTypo3\Solr\SuggestQuery is deprecated, use ApacheSolrForTypo3\Solr\Domain\Search\Query\SuggestQuery now

Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors for this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Andreas Lappe
* Andri Steiner
* Benni Mack
* Daniel Diesenreither
* Daniel Mann
* Daniel Ruf
* Georg Ringer
* Hannes Lau
* Jeffrey Nellissen
* Jens Jacobsen
* Marco Bresch
* Marcus Schwemer
* Markus Friedrich
* Markus Kobligk
* Markus Sommer
* Nicole Cordes
* Patrick Schriner
* P. Golmann
* Rafael Kähm
* Sascha Egerer
* Simon Schmidt
* Thomas Löffler
* Timo Hund
* Tomas Norre Mikkelsen

Also a big thanks to our partners that have joined the EB2018 program:

* Albervanderveen
* Amedick & Sommer
* AUSY SA
* bgm Websolutions GmbH
* Citkomm services GmbH
* Consulting Piezunka und Schamoni - Information Technologies GmbH
* Cows Online GmbH
* food media Frank Wörner
* FTI Touristik GmbH
* Hirsch & Wölfl GmbH
* Hochschule Furtwangen
* JUNGMUT Communications GmbH
* Kreis Coesfeld
* LOUIS INTERNET GmbH
* L.N. Schaffrath DigitalMedien GmbH
* Mercedes AMG GmbH
* Petz & Co
* Pluswerk AG
* ressourcenmangel an der panke GmbH
* Site'nGo
* Studio B12 GmbH
* systime
* Talleux & Zöllner GbR
* TOUMORO
* TWT Interactive GmbH

Special thanks to our premium EB 2018 partners:

* b13 http://www.b13.de/
* dkd http://www.dkd.de/
* jweiland.net http://www.jweiland.net/

Thanks to everyone who helped in creating this release!

Outlook
=======

In the next release we want to focus on the move to solarium and the support of the lastest Apache Solr version.

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


