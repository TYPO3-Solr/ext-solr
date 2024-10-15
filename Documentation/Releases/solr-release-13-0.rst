.. include:: ../Includes.rst.txt
.. _releases-13-0:

=============
Releases 13.0
=============


Release 13.0.0-beta-1
=====================

This is a first beta release for TYPO3 13.4 LTS

New in this release
-------------------

Adjust mount point indexing
~~~~~~~~~~~~~~~~~~~~~~~~~~~

Mount point indexing and corresponding tests have been adjusted for TYPO3 13. Mount points are supported in general and the mounted pages will be indexed like standard pages.

But there is a point to consider: Mounted pages from a pagetree without a site configuration cannot be indexed, in fact TYPO3 currently can't mount a page from a page tree without a site configuration and an exeception occurs.
The behavior is intentionally designed this way in TYPO3 core, the background is that it is not possible to specify the languages of the mounted page tree without Site Configuration.

.. note::
   We require at least TYPO3 13.4.2, as this version contains some bugfixes that address problems with the determination of TypoScript and the site configuration of mounted pages.

Release 13.0.0-alpha-1
======================

This is a first alpha release for upcoming TYPO3 13 LTS

New in this release
-------------------

!!! Upgrade to Apache Solr 9.7.0
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

This release requires Apache Solr v 9.7.0+.

All Changes
-----------

*  [TASK] Use request object to retrieve query params instead of _GET by @sfroemkenjw in `#4045 <https://github.com/TYPO3-Solr/ext-solr/pull/4045>`_
*  [TASK] Use Attributes for PHPUnit tests by @bmack in `#4048 <https://github.com/TYPO3-Solr/ext-solr/pull/4048>`_
*  [TASK] Update PHP-Stan to at least 1.11.* by @sfroemkenjw in `#4055 <https://github.com/TYPO3-Solr/ext-solr/pull/4055>`_
*  [TASK] Apply and repair rector refactorings by @sfroemkenjw in `#4049 <https://github.com/TYPO3-Solr/ext-solr/pull/4049>`_
*  [TASK] Migrate requireJS to ES6. Solr BE Modal JS by @sfroemkenjw in `#4057 <https://github.com/TYPO3-Solr/ext-solr/pull/4057>`_
*  [TASK] Apache Solr 9.6 compatibility by @dkd-friedrich in `#4056 <https://github.com/TYPO3-Solr/ext-solr/pull/4056>`_
*  [TASK] Use new template module API by @sfroemkenjw in `#4054 <https://github.com/TYPO3-Solr/ext-solr/pull/4054>`_
*  [FEATURE] Add contentObjectData to searchController by @spoonerWeb in `#4059 <https://github.com/TYPO3-Solr/ext-solr/pull/4059>`_
*  [BUGFIX] Add empty array as fallback if null by @spoonerWeb in `#4061 <https://github.com/TYPO3-Solr/ext-solr/pull/4061>`_
*  [BUGFIX] Add empty array defaults in SearchFormViewHelper by @hnadler in `#4042 <https://github.com/TYPO3-Solr/ext-solr/pull/4042>`_
*  [TASK] Integrate content of Module layout into WithPageTree by @sfroemkenjw in `#4066 <https://github.com/TYPO3-Solr/ext-solr/pull/4066>`_
*  [TASK] Repair statistics chart because of CSP in Solr Info module by @sfroemkenjw in `#4068 <https://github.com/TYPO3-Solr/ext-solr/pull/4068>`_
*  [FEATURE:BP:12] Be able to disable tracking of last searches by @dkd-kaehm in `#4064 <https://github.com/TYPO3-Solr/ext-solr/pull/4064>`_
*  [TASK] Add access plugin tests by @dkd-friedrich in `#4069 <https://github.com/TYPO3-Solr/ext-solr/pull/4069>`_
*  [TASK] Update authors by @sfroemkenjw in `#4071 <https://github.com/TYPO3-Solr/ext-solr/pull/4071>`_
*  [TASK] Remove content stream usage by @dkd-friedrich in `#4073 <https://github.com/TYPO3-Solr/ext-solr/pull/4073>`_
*  [BUGFIX] Fix synonym and stop word upload by @dkd-friedrich in `#4074 <https://github.com/TYPO3-Solr/ext-solr/pull/4074>`_
*  [TASK] Call getLabelFromItemListMerged with the current row data by @3l73 in `#4081 <https://github.com/TYPO3-Solr/ext-solr/pull/4081>`_
*  [BUGFIX] numeric facet range slider sends lot of requests to server by @hvomlehn-sds in `#4084 <https://github.com/TYPO3-Solr/ext-solr/pull/4084>`_
*  [BUGFIX] Typecast $userGroup to integer by @derhansen in `#4079 <https://github.com/TYPO3-Solr/ext-solr/pull/4079>`_
*  [TASK] Remove getIsSiteManagedSite as all site are managed now by @sfroemkenjw in `#4070 <https://github.com/TYPO3-Solr/ext-solr/pull/4070>`_
*  [BUG] #4026 treat non-overlayed mount points as valid by @derMatze82 in `#4029 <https://github.com/TYPO3-Solr/ext-solr/pull/4029>`_
*  [TASK] New Crowdin updates by @dkd-kaehm in `#4094 <https://github.com/TYPO3-Solr/ext-solr/pull/4094>`_
*  [BUGFIX] Fix range string calculation in DateRange facet by @derhansen in `#4090 <https://github.com/TYPO3-Solr/ext-solr/pull/4090>`_
*  [FIX:12] scheduler task "Optimize index of a site" is not functional by @dkd-kaehm in `#4104 <https://github.com/TYPO3-Solr/ext-solr/pull/4104>`_
*  [TASK] Apache Solr 9.6.1 compatibility by @dkd-kaehm in `#4106 <https://github.com/TYPO3-Solr/ext-solr/pull/4106>`_
*  [FIX] tests for TYPO3 13 @ 2024.07.02 by @dkd-kaehm in `#4098 <https://github.com/TYPO3-Solr/ext-solr/pull/4098>`_
*  [FIX] deprecations for Fluid viewHelpers and stack by @dkd-kaehm in `#4140 <https://github.com/TYPO3-Solr/ext-solr/pull/4140>`_
*  [FIX] Integration\\Extbase\\PersistenceEventListenerTest errors by @dkd-kaehm in `#4142 <https://github.com/TYPO3-Solr/ext-solr/pull/4142>`_
*  [TASK] TYPO3 13 dev-main 2024.09.13 compatibility:: Tests by @dkd-kaehm in `#4153 <https://github.com/TYPO3-Solr/ext-solr/pull/4153>`_
*  [TASK] TYPO3 13 compatibility 2024.09.19 by @dkd-kaehm in `#4159 <https://github.com/TYPO3-Solr/ext-solr/pull/4159>`_
*  [FIX] Tests for TYPO3 dev-main @2024.09.23 by @dkd-kaehm in `#4163 <https://github.com/TYPO3-Solr/ext-solr/pull/4163>`_
*  [BUGFIX] Failed to resolve module specifier '@apache-solr-for-typo3/solr//FormModal.js' by @dkd-kaehm in `#4166 <https://github.com/TYPO3-Solr/ext-solr/pull/4166>`_
*  [BUGFIX] @typo3/backend/tree/page-tree-element does not work in BE-Modules by @dkd-kaehm in `#4167 <https://github.com/TYPO3-Solr/ext-solr/pull/4167>`_
*  [FIX] access restrictions stack for TYPO3 13 by @dkd-kaehm in `#4172 <https://github.com/TYPO3-Solr/ext-solr/pull/4172>`_
*  [TASK] Adapt simulated environment for TYPO3 13 by @dkd-friedrich in `#4164 <https://github.com/TYPO3-Solr/ext-solr/pull/4164>`_
*  [DOCS] Update TxSolrSearch.rst by @seirerman in `#4162 <https://github.com/TYPO3-Solr/ext-solr/pull/4162>`_
*  [TASK] Update dependencies by @dkd-kaehm in `#4177 <https://github.com/TYPO3-Solr/ext-solr/pull/4177>`_
*  [TASK] fix CS issues for newest typo3/coding-standards by @dkd-kaehm in `#4177 <https://github.com/TYPO3-Solr/ext-solr/pull/4177>`_


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

*  Benni Mack
*  @derMatze82
*  Hendrik vom Lehn
*  @hnadler
*  Lars Tode
*  Markus Friedrich
*  Rafael Kähm
*  Stefan Frömken
*  Thomas Löffler
*  Torben Hansen

Also a big thank you to our partners who have already concluded one of our new development participation packages such
as Apache Solr EB for TYPO3 13 LTS (Feature):

- b13 GmbH
- Berlin-Brandenburgische Akademie der Wissenschaften
- in2code GmbH
- mehrwert intermediale kommunikation GmbH

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
