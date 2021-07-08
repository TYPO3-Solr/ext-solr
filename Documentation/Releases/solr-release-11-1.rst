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


Apache Solr 8.8.2 support
-------------------------

With EXT:solr 11.1 we support Apache Solr 8.8.2, the latest release of Apache Solr.

To see what has changed in Apache Solr please read the release notes of Apache Solr:
https://solr.apache.org/docs/8_8_2/changes/Changes.html

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

* ...

Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* ...

Also a big thanks to our partners that have joined the EB2021 program:

* ...

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


