.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _releases-9:

===========================
Apache Solr for TYPO3 9.0.0
===========================

We are happy to release EXT:solr 9.0.0. The focus of EXT:solr 9.0.0 was, to support the latest version of Apache Solr (7.6.0) and to drop the usage of the solrphpclient and use the solarium php API instead.

**Important**: This version is installable with TYPO3 9 LTS, but does **not** support all features of TYPO3 9 yet. Especially the site handling needs further development in EXT:solr to fully support it with TYPO3 9 LTS. Beside the open tasks in EXT:solr there are also parts left in the TYPO3 core (e.g. when using language fallbacks). In the next release of EXT:solr we want to improve the integration with the site management in TYPO3. Since the development budget is limited at one side and we have other project requests at the other side we could spend less time on the development of EXT:solr by the end of the year. If you want to support us please consider to sponsor us in 2019.

New in this release
===================

Support of Apache Solr 7.6
--------------------------

EXT:solr 9.0.0 ships a ready to use docker container with Apache Solr 7.6. This makes new features of Apache Solr available to EXT:solr.

Replaced solrphpclient with solarium php api
--------------------------------------------

For the communication between EXT:solr we've used the solrphpclient library. This library was not maintained anymore and had several custom modifications. Therefore we made the decision to move to the solarium php api.

This brings us the following advantages:

* Use a common, robust, maintained library
* Join the forces with other PHP projects to improve solarium and benefit from that

The migration to solarium required several changes in EXT:solr and all add-on's and we will provide compabtility releases for them as well.

With the move to solarium we donated some parts to the solarium API (e.g. the solr core handling). This allows us to remove some redundant logic in EXT:solr in the future.

Thanks:

* Thanks to the whole solarium team (https://github.com/orgs/solariumphp/people) and Markus Kalkbrenner for the work on solarium and the support during the integration into EXT:solr

Pull requests and Links:

* https://github.com/TYPO3-Solr/ext-solr/pull/2202
* https://github.com/TYPO3-Solr/ext-solr/pull/2103
* https://github.com/TYPO3-Solr/ext-solr/pull/2070
* https://github.com/solariumphp/solarium/pull/625

Outlook:

By now we use the Queries and Httpclient of solarium, but not the domain classes because this requires additional changes in EXT:solr and solarium.

In the future we want to get rid of redundant code and use the API where we can and it makes sence and support solarium with the features that we need for EXT:solr.

TYPO3 9 compatibility
---------------------

The current release is installable and useable with TYPO3 9 LTS **but not all features** are supported.

Currently it is supported to:

* Use EXT:solr with sites that do have a domain record or domain configuration from EXT:solr

The following parts require additional work and are not supported:

* SiteHandling
* Extensionscanner proofed

Since a backward compatibility to TYPO3 8.7 LTS make it harder to support the previous mentioned topics. We will drop the support for TYPO3 8 LTS
in the next version and improve the support of those TYPO3 9 LTS features.

Nevertheless a lot of work was allready done for the basic support of TYPO3 9 LTS in the following pull requests:

* https://github.com/TYPO3-Solr/ext-solr/pull/2169
* https://github.com/TYPO3-Solr/ext-solr/pull/1954
* https://github.com/TYPO3-Solr/ext-solr/pull/1799
* https://github.com/TYPO3-Solr/ext-solr/pull/1796
* https://github.com/TYPO3-Solr/ext-solr/pull/1774

Allow open query in DateRangeFacet
----------------------------------

This patch allows to create data range facets with an open beginning or open end.

* https://github.com/TYPO3-Solr/ext-solr/pull/2038

Support to differ between read and write connections
----------------------------------------------------

By now each site had one solr connection for reading and writing. In most of the cases this good enough when you want to index and search in the same core.

Some setups require a more flexible approach:

* E.g. when you want to clean a core and re-index that data your index is not complete for some time on the live site
* When you want to do a master/slave setup for performance reasons(e.g. by using a slave node on the web server) this was not possible by now

With a separation of read and write connections this is now possible. With these building blocks you could e.g.

* Index into a shadow core (that is the write core) and swap read and write core when your re-index is done
* Install a slave server on your frontend server and index into a dedicated master node that act's as a solr master server

The new setup can be configured like that:

::

    plugin.tx_solr.solr {
            read {
                scheme = https
                host   = 127.0.0.1
                port   = 8983
                path   = /solr/core_en/
            }
            write < .read
            write {
                port   = 8984
            }
    }

For compatibility reasons EXT:solr is falling back to ```plugin.tx_solr.solr.*``` when nothing is configured here:

Important: When you update from EXT:solr 8.1.0 you need to re-initialize your EXT:solr connections.

* https://github.com/TYPO3-Solr/ext-solr/pull/2134

More flexible facet uri ViewHelpers with optional facet object
--------------------------------------------------------------

You could create now a facet item link (add, set, remove) somewhere else in the results view where no facet object is available.

Beside

```
{s:uri.facet.setFacetItem(facet: facet, facetItem: option)}
```
you could create a set link now with this vh arguments:

```
{s:uri.facet.setFacetItem(facetName: 'type', facetItemValue: 'pages', resultSet: resultSet)}
```

Thanks to Marc Bastian Heinrichs for creating a patch for that.

* https://github.com/TYPO3-Solr/ext-solr/pull/2194


Allow to configure additionalExcludeTags for option facets
----------------------------------------------------------

When you want to exclude facets from the counts of another facets, Apache Solr uses tags and excludeTags to realize that.

With the setting ```additionalExcludeTags``` you can add custom exclude tags for a facet and ```addFieldAsTag``` allows you, to force the creation of a tag for a certain facet.

Thanks to Marc Bastian Heinrichs for creating a patch for that and to in2code for paying for the finalization and documentation.

* https://github.com/TYPO3-Solr/ext-solr/issues/2195


Bugfixes
========

* https://github.com/TYPO3-Solr/ext-solr/pull/2048 Fixes a warning in the TranslateViewHelper
* https://github.com/TYPO3-Solr/ext-solr/pull/2052 Use copy instead of reference in the TypoScript template
* https://github.com/TYPO3-Solr/ext-solr/pull/2053 Unify multiple whitespaces to a single whitespace
* https://github.com/TYPO3-Solr/ext-solr/pull/2245 KeepAllFacetsOnSelection is not evaluated when KeepAllOptionsOnSelection is used

Migration from EXT:solr 8.1.0 to EXT:solr 9.0.0
===============================================

* We ship Apache Solr 7.5.0, you need to install that Version with our configSet.
* The argument "hasSearched" was removed from the searchAction and is no longer passed. You can now retrieve this
  information by calling "SearchResultSet::getHasSearch" or "{resultSet.hasSearched}" in the FLUID template.
  When you access this argument in your FLUID Template, you need to change that as well.
* EXT:solr 9 differs between read and write connections now. As fallback the old configuration is still supported and used for reading and writing.
  Nevertheless you need to re-initialize the solr connections that the data in the registry is rewritten. If you want to make use of the new configuration
  you can configure the connections like that:

::

    plugin.tx_solr.solr {
        read {
            scheme = https
            host   = 127.0.0.1
            port   = 8983
            path   = /solr/core_en/
        }
        write < .read
        write {
            port   = 8984
        }
    }

Removed Code
============

The following code parts have been removed as announced in previous versions of EXT:solr:

* SearchResultSetService::getHasSearched Please use SearchResultSet::getHasSearched now
* SortingHelper::getSortFields
* SortingHelper::getSortOptions
* Queue::initialize
* Queue::initializeIndexingConfigurations
* Search::hasSearched
* Search::getResultDocumentsRaw
* Search::getResultDocumentsEscaped
* Search::getFacetCounts
* Search::getFacetFieldOptions
* Search::getFacetQueryOptions
* Search::getFacetRangeOptions
* Search::getSpellcheckingSuggestions
* Util::isLocalizedRecord

Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Aljoscha Weber
* Benni Mack
* Felix Nagel
* Florian Wessels
* Helmut Hummel
* Jens Jacobsen
* Marc Bastian Heinrichs
* Patrick Gaumond
* Rafael Kähm
* Sasche Egerer
* Thomas Hohn
* Timo Hund

Also a big thanks to our partners that have joined the EB2019 program:

* Amedick & Sommer Neue Medien GmbH
* BIBUS AG Group
* Bitmotion GmbH
* CS2 AG
* Gernot Leitgab
* Getdesigned GmbH
* Hirsch & Wölfl GmbH
* ITK Rheinland
* Kassenärztliche Vereinigung Bayerns (KZVB)
* TOUMORO
* Ueberbit Gmbh
* XIMA MEDIA GmbH
* b13 GmbH
* bgm business websolutions GmbH & Co KG
* datamints GmbH
* medien.de mde GmbH
* mehrwert intermediale kommunikation GmbH
* mellowmessage GmbH
* plan2net GmbH
* punkt.de GmbH

Special thanks to our premium EB 2019 partners:

* jweiland.net
* sitegeist media solutions GmbH


In addition i want to thank Markus Kalkbrenner and the whole solarium team for the support.

Thanks to everyone who helped in creating this release!

Outlook
=======

In the next release we will drop the support of TYPO3 8 and focus on the integration into TYPO39. Depending on the funding we would like to support
the integration into the TYPO3 site management and want to allow to configure you Solr site with the TYPO3 site management module.

With the move to the solarium php api, we take the first step of the integration. In the next releases we want to use more parts of the solarium API and also contribute to that API to share the improvements with other PHP projects.


How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us in 2019 by becoming an EB partner:

http://www.typo3-solr.com/en/contact/

or call:

+49 (0)69 - 2475218 0


