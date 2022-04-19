.. include:: ../Includes.rst.txt


.. _releases-11-2:

============================
Apache Solr for TYPO3 11.2.0
============================

We are happy to release EXT:solr 11.2.0.
The focus of this release has been on supporting the latest Apache Solr version 8.11.1 and on optimizing the data update monitoring.

New in this release
===================

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

* [TASK] Upgrade Solarium to 6.0.4 .. __: https://github.com/TYPO3-Solr/ext-solr/issues/3178
* [BUGFIX] Fix thrown exception in Synonym and StopWordParser .. __: https://github.com/TYPO3-Solr/ext-solr/commit/300325221d9b4ec38b83b6d5e985d8d95ab1f9c5
* [BUGFIX] TER releases missing composer dependencies .. __: https://github.com/TYPO3-Solr/ext-solr/issues/3176
* [TASK] Configure CI matrix for release 11.2 .. __: https://github.com/TYPO3-Solr/ext-solr/commit/5a3843e191a2d3924412a43b54b48ba399e00036
* [BUGFIX:BP:11.1] Fix autosuggest with non-ascii terms .. __: https://github.com/TYPO3-Solr/ext-solr/issues/3096
* [BUGFIX] Prevent unwanted filter parameters from being generated .. __: https://github.com/TYPO3-Solr/ext-solr/issues/3126
* [TASK] Add Czech translation .. __: https://github.com/TYPO3-Solr/ext-solr/issues/3132
* [TASK] Replace mirrors for Apache Solr binaries on install-solr.sh .. __: https://github.com/TYPO3-Solr/ext-solr/issues/3094
* [BUGFIX:BP:11-1] routeenhancer with empty filters .. __: https://github.com/TYPO3-Solr/ext-solr/issues/3099
* [TASK] Use Environment::getContext() instead of GeneralUtility .. __: https://github.com/TYPO3-Solr/ext-solr/commit/7cde5222a6203ab97d353d8eca723fa3fa924e48
* [BUGFIX] Don't use jQuery.ajaxSetup() .. __: https://github.com/TYPO3-Solr/ext-solr/issues/2503
* [TASK] Setup Github Actions :: Basics .. __: https://github.com/TYPO3-Solr/ext-solr/commit/e545d692ce41133fcff8ec1d294b0a9d0e68bd2a
* [TASK] Setup Dependabot to watch "solarium/solarium" .. __: https://github.com/TYPO3-Solr/ext-solr/commit/561815044e3651a0aaa8fa2ad4de5e2c3ccf4e3e
* [BUGFIX] Filter within route enhancers .. __: https://github.com/TYPO3-Solr/ext-solr/issues/3054
* [BUGFIX] Fix NON-Composer mod libs composer.json for composer v2 .. __: https://github.com/TYPO3-Solr/ext-solr/issues/3053
* ... See older commits, which are a part of previous releases: https://github.com/TYPO3-Solr/ext-solr/commits/master?after=d3f9a919d44f8a72b982bdde131408b571ff02c8+139&branch=release-11-2


Contributors
============

Special thanks to ACO Ahlmann SE & Co. KG for sponsoring the improved data update handling, [#3153](https://github.com/TYPO3-Solr/ext-solr/issues/3153)!

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

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us by becoming an EB partner:

https://shop.dkd.de/Produkte/Apache-Solr-fuer-TYPO3/

or call:

+49 (0)69 - 2475218 0
