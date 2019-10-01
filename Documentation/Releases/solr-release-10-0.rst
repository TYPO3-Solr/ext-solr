.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _releases-10:

============================
Apache Solr for TYPO3 10.0.0
============================

We are happy to release EXT:solr 10.0.0.
The focus of EXT:solr 10.0.0 was, to support introduced in TYPO3 9 LTS `site handling <https://docs.typo3.org/m/typo3/reference-coreapi/9.5/en-us/ApiOverview/SiteHandling/Index.html />`_.

**Important**: This version is installable with TYPO3 9 LTS only.
Since the development budget is limited at one side and we have other project requests at the other side we could spend less time on the development of EXT:solr.
If you want to support us please consider to sponsor us.

New in this release
===================

TBD

Migration from EXT:solr 9 to EXT:solr 10
========================================

Important things on sites with activated Site Handling
------------------------------------------------------

By activating TYPO3 Site-Handling some classical things(configs, functions, etc.), that worked in TYPO3 prior 9.5 LTS, will become obsolete.
Following things will become standard, and should be preferred and activated/configured as close as possible and in some cases immediately,
otherwise the things wil not work or break the whole setup:

TypoScript
~~~~~~~~~~

plugin.tx_solr.solr
"""""""""""""""""""

This TypoScript configurations for Constants and for Setup are at least partially obsolete and are ignored on Site Handling activated sites.
All Apache Solr connections must be stored in Site Handling "config.yaml" file for each language.

config.absRefPrefix
"""""""""""""""""""

The `"config.absRefPrefix" <https://docs.typo3.org/m/typo3/reference-typoscript/master/en-us/Setup/Config/Index.html#absrefprefix />`_ is `obsolete <https://docs.typo3.org/c/typo3/cms-core/9.5/en-us/Changelog/9.4/Feature-86057-ImprovedTypolinkURLLinkGeneration.html />`_ and must be replaced with
Site Handlings `"base" <https://docs.typo3.org/m/typo3/reference-coreapi/9.5/en-us/ApiOverview/SiteHandling/Basics.html#base />`_ or `"baseVariants" <https://docs.typo3.org/m/typo3/reference-coreapi/9.5/en-us/ApiOverview/SiteHandling/BaseVariants.html />`_ settings.

Removed Code
============

The following code parts have been removed as announced in previous versions of EXT:solr:

TBD

Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* TBD
* Rafael Kähm
* TBD
* Timo Hund

Also a big thanks to our partners that have joined the EB program:

* TBD

Special thanks to our premium EB partners:

* TBD

Outlook
=======

TBD

How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us by becoming an EB partner:

http://www.typo3-solr.com/en/contact/

or call:

+49 (0)69 - 2475218 0


