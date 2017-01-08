.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt

.. raw:: latex

    \newpage

.. raw:: pdf

   PageBreak

.. _conf-tx-solr-statistics:

tx_solr.statistics
===================

This section allows you to configure the logging for statistics.

**Note**: The statistics are logged into a mysql table. This might not make sense for high frequently used searches. In this case you should think about to connect a dedicated tracking tool.

statistics
----------

:Type: Boolean
:TS Path: plugin.tx_solr.statistics
:Since: 2.0
:Default: 0

Set `plugin.tx_solr.statistics = 1` to log statistics.


statistics.anonymizeIP
----------------------

:Type: Boolean
:TS Path: plugin.tx_solr.statistics.anonymizeIP
:Since: 2.0
:Default: 0

Anonymizes the ip address in the logging records.

tx_solr.advancedStatistics
===================

This section allows you to configure the logging for advancedStatistics. It fills the columns `time_total`, `time_preparation` and `time_processing`
in the table `tx_solr_statistics` with values returned from the search query.

**Note**: Enabling advancedStatistics can have performance impact since debugMode is appended to queries - requires that
`plugin.tx_solr.statistics = 1` has been set.

advancedStatistics
----------

:Type: Boolean
:TS Path: plugin.tx_solr.advancedStatistics
:Since: 6.1
:Default: 0

Set `plugin.tx_solr.advancedStatistics = 1` to enable advanced statistics
