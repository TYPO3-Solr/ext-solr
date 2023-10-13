.. include:: /Includes.rst.txt


.. _conf-tx-solr-settings:

Extension Configuration
=======================

The following settings can be defined in the "Settings":>"Extension Configuration":>"solr"

pluginNamespaces
----------------

:Type: String
:Since: 11.1
:Default: tx_solr

A list of white listed plugin namespaces (Required by cacheHash/excludedParameters and plugin flex form).

..  note::
    This list only is available in Plugin -> Options -> Plugin Namespace.

includeGlobalQParameterInCacheHash
----------------------------------

:Type: Boolean
:Since: 11.1
:Default: 0

Include/Exclude global q parameter in/from cacheHash.


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

useConfigurationMonitorTables
-----------------------------

:Type: String
:Since: 6.1
:Default:

Monitor tables - explicitly monitor these (still requires TypoScript configuration)

allowSelfSignedCertificates
---------------------------

:Type: Boolean
:Since: 6.1
:Default: 0

Can be used to allow self signed certificates - when using the SSL protocol.

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

enableRouteEnhancer
-------------------

:Type: Boolean
:Since: 12.0
:Default: 0

To use the EXT:solr possibility to create speaking URLs for Solr facets, activate this option.

As this feature requires additional configuration and costly processing, it's disabled by default.