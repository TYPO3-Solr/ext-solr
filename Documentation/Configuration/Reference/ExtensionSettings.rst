.. include:: /Includes.rst.txt


.. _conf-tx-solr-settings:

Extension Configuration
=======================

The following settings can be defined in the "Settings":>"Extension Configuration":>"solr"

useConfigurationFromClosestTemplate
-----------------------------------

:Type: Boolean
:Since: 6.1
:Default: 0

When this setting is active the closest page with a TypoScript template will be used to fetch the configuration.
This improves the performance but limits also the possibilities. E.g. conditions can not be used that are related to a certain page.

useConfigurationTrackRecordsOutsideSiteroot
-------------------------------------------

:Type: Boolean
:Since: 6.1
:Default: 1

A common common scenario is to have a site and a storage folder for records parallel to it
on the same level (f.e.)
If you don't want this behaviour - it should be set to false.

allowSelfSignedCertificates
---------------------------

:Type: Boolean
:Since: 6.1
:Default: 0

Can be used to allow self signed certificates - when using the SSL protocol.


allowLegacySiteMode
-------------------

:Type: Boolean
:Since: 10.0
:Default: 0

Can be used to allow using classic TypoScript Configuration for Sites.

monitoringType
--------------

:Type: Int
:Since: 11.2
:Default: 0

Defines how data updates should be monitored

By default (=0) recognized updates will be processed directly and the Solr index queue will be directly updated, also
the Solr index where appropriate. As in huge instances the monitoring can slow down the TYPO3 backend, two more monitoring
options are available:

- Delayed: Record update events will be queue and processed later, the scheduler task "Event Queue Worker" is required for processing.
- No monitoring: Monitoring is completely disabled, please note that you have to take care of Solr index updates yourself.
