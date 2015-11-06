.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _conf-tx-solr-logging:

tx_solr.logging
===============

This section defines logging options. All loggings will be available in the devlog.

.. contents::
   :local:


exceptions
----------

:Since: 1.0
:Default: 1
:Options: 0,1
:Type: Boolean

If enabled, thrown exceptions are logged.

indexing
--------

:Since: 1.0
:Default: 0
:Options: 0,1
:Type: Boolean

If enabled, logs when pages / documents are indexed.

indexing.indexQueueInitialization
---------------------------------

:Since: 2.0
:Default: 0
:Options: 0,1
:Type: Boolean

If enabled, logs the query used to initialize the indexing queue.

indexing.indexQueuePageIndexerGetData
-------------------------------------

:Since: 2.0
:Default: 0
:Options: 0,1
:Type: Boolean

If enabled, the requested data will be logged. Request data includes item, url, parameters, headers, data, decodedData and report.

query.filters
-------------

:Since: 1.0
:Default: 0
:Options: 0,1
:Type: Boolean

If enabled, filters will be logged when they get added to the Solr query.

query.searchWords
-----------------

:Since: 1.0
:Default: 0
:Options: 0,1
:Type: Boolean

If enabled, received search queries will be logged.

query.queryString
-----------------

:Since: 1.0
:Default: 0
:Options: 0,1
:Type: Boolean

If enabled, query string parameters and the respective response will be logged.

query.rawPost
-------------

:Since: 1.0
:Default: 0
:Options: 0,1
:Type: Boolean

If enabled, POST requests against the Solr server will be logged.

query.rawGet
------------

:Since: 1.0
:Default: 0
:Options: 0,1
:Type: Boolean

If enabled, GET requests against the Solr server will be logged.