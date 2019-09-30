.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


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

:Type: Integer
:TS Path: plugin.tx_solr.statistics.anonymizeIP
:Since: 2.0
:Default: 0

Defines the number of octets of the IP address to anonymize in the statistics log records.

statistics.addDebugData
-----------------------

:Type: Boolean
:TS Path: plugin.tx_solr.statistics.addDebugData
:Since: 6.1
:Default: 0

Adds debug data to the columns `time_total`, `time_preparation` and `time_processing` in the table `tx_solr_statistics`
from the result of the search query.

**Note**: Enabling addDebugData can have performance impact since debugMode is appended to queries.
