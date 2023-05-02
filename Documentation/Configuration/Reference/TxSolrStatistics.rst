.. include:: /Includes.rst.txt


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

Allowed values are 0 (masking disabled), 1 (mask host), 2 (mask host and subnet).

statistics.addDebugData
-----------------------

:Type: Boolean
:TS Path: plugin.tx_solr.statistics.addDebugData
:Since: 6.1
:Default: 0

Adds debug data to the columns `time_total`, `time_preparation` and `time_processing` in the table `tx_solr_statistics`
from the result of the search query.

**Note**: Enabling addDebugData can have performance impact since debugMode is appended to queries.

statistics.topHits.days
-----------------------

:Type: Integer
:TS Path: plugin.tx_solr.statistics.topHits.days
:Since: 12.0
:Default: 30

Number of days to read out the search top hits.

statistics.topHits.limit
------------------------

:Type: Integer
:TS Path: plugin.tx_solr.statistics.topHits.limit
:Since: 12.0
:Default: 5

Number of records to read out the search top hits.

statistics.noHits.days
----------------------

:Type: Integer
:TS Path: plugin.tx_solr.statistics.noHits.days
:Since: 12.0
:Default: 30

Number of days to read out non-search hits.

statistics.noHits.limit
-----------------------

:Type: Integer
:TS Path: plugin.tx_solr.statistics.noHits.limit
:Since: 12.0
:Default: 5

Number of records to read out non-search hits.

statistics.queries.days
-----------------------

:Type: Integer
:TS Path: plugin.tx_solr.statistics.queries.days
:Since: 12.0
:Default: 30

Number of days to read out search queries.

statistics.queries.limit
-----------------------

:Type: Integer
:TS Path: plugin.tx_solr.statistics.queries.limit
:Since: 12.0
:Default: 100

Number of records to read out search queries.