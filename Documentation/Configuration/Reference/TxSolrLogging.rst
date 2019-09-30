.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _conf-tx-solr-logging:

tx_solr.logging
===============

This section defines logging options. All loggings will be available in the TYPO3 logging framework.

.. contents::
   :local:

debugOutput
-----------

:Type: Boolean
:TS Path: plugin.tx_solr.logging.debugOutput
:Default: 0
:Options: 0,1
:Since: 6.1

If enabled the written log entries will be printed out as debug message in the frontend or to the TYPO3 debug console in the backend.
This setting replaces the previous setting `plugin.tx_solr.logging.debugDevLogOutput` which was needed, when the devLog was used.

exceptions
----------

:Type: Boolean
:TS Path: plugin.tx_solr.logging.exceptions
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, thrown exceptions are logged.

indexing
--------

:Type: Boolean
:TS Path: plugin.tx_solr.logging.indexing
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, logs when pages / documents are indexed.

indexing.indexQueueInitialization
---------------------------------

:Type: Boolean
:TS Path: plugin.tx_solr.logging.indexing.indexQueueInitialization
:Default: 1
:Options: 0,1
:Since: 2.0

If enabled, logs the query used to initialize the indexing queue.

indexing.indexQueuePageIndexerGetData
-------------------------------------

:Type: Boolean
:TS Path: plugin.tx_solr.logging.indexing.indexQueuePageIndexerGetData
:Default: 1
:Options: 0,1
:Since: 2.0

If enabled, the requested data will be logged. Request data includes item, url, parameters, headers, data, decodedData and report.

query.filters
-------------

:Type: Boolean
:TS Path: plugin.tx_solr.logging.query.filters
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, filters will be logged when they get added to the Solr query.

query.searchWords
-----------------

:Type: Boolean
:TS Path: plugin.tx_solr.logging.query.searchWords
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, received search queries will be logged.

query.queryString
-----------------

:Type: Boolean
:TS Path: plugin.tx_solr.logging.query.queryString
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, query string parameters and the respective response will be logged.

query.rawPost
-------------

:Type: Boolean
:TS Path: plugin.tx_solr.logging.query.rawPost
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, POST requests against the Solr server will be logged.

query.rawGet
------------

:Type: Boolean
:TS Path: plugin.tx_solr.logging.query.rawGet
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, GET requests against the Solr server will be logged.