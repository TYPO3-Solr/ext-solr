.. _releases-14-0:

=============
Releases 14.0
=============

Release 14.0.0
==============

This is a new major release for TYPO3 14 LTS.

New in this release
-------------------

.. note::
   This section will be extended as features are finalized.


Breaking Changes
----------------

Unified Site Hash Strategy
~~~~~~~~~~~~~~~~~~~~~~~~~~

Introduced in solr v13.1, and now implemented as default, the site hash
strategy is now based on the site identifier and not on the domain anymore,
making the site hash calculation more robust across sites with multiple domains.

The extension configuration setting: `siteHashStrategy` has been removed
without substitution.

The PSR-14 event :php:`AfterDomainHasBeenDeterminedForSiteEvent` has been
removed, as it has been superseded by
:php:`AfterSiteHashHasBeenDeterminedForSiteEvent`.

If you upgrade from < 13.1, it is recommended to re-index all solr cores
completely.


!!! QueueInitializationServiceAwareInterface and related Queue methods removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The interface
:php:`ApacheSolrForTypo3\Solr\IndexQueue\QueueInitializationServiceAwareInterface`
and its implementation in :php:`ApacheSolrForTypo3\Solr\IndexQueue\Queue` have been
removed entirely. The following public API is gone:

*   :php:`Queue::setQueueInitializationService(QueueInitializationService $service): void`
*   :php:`Queue::getQueueInitializationService(): QueueInitializationService`
*   :php:`Queue::getInitializationService(): QueueInitializationService` (was already deprecated since v12)

The :php:`QueueInitializationService` itself is not affected and continues to exist.

Background
""""""""""

The interface was introduced as a workaround for a circular dependency: the
:php:`QueueInitializationService` created :php:`Queue` instances and then injected itself
back via :php:`setQueueInitializationService()`. In practice, the injected service was
never used by :php:`Queue` internally, and :php:`getQueueInitializationService()` was
only called in tests – never in production code. The pattern was obsolete.
>>>>>>> ee5cc83d7 ([!!!][TASK] Remove QueueInitializationServiceAwareInterface and related Queue API)


All Changes
-----------



Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

.. note::
   Contributors will be listed here once the release is finalized.

Also a big thank you to our partners who have already concluded one of our new development participation packages such
as Apache Solr EB for TYPO3 14 LTS (Feature).


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
