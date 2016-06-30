.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _conf-tx-solr-logging:

.. raw:: latex

    \newpage

.. raw:: pdf

   PageBreak


tx_solr.logging
===============

This section defines logging options. All loggings will be available in the devlog.

.. contents::
   :local:


exceptions
----------

:Type: Boolean
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, thrown exceptions are logged.

indexing
--------

:Type: Boolean
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, logs when pages / documents are indexed.

indexing.indexQueueInitialization
---------------------------------

:Type: Boolean
:Default: 1
:Options: 0,1
:Since: 2.0

If enabled, logs the query used to initialize the indexing queue.

indexing.indexQueuePageIndexerGetData
-------------------------------------

:Type: Boolean
:Default: 1
:Options: 0,1
:Since: 2.0

If enabled, the requested data will be logged. Request data includes item, url, parameters, headers, data, decodedData and report.

query.filters
-------------

:Type: Boolean
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, filters will be logged when they get added to the Solr query.

query.searchWords
-----------------

:Type: Boolean
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, received search queries will be logged.

query.queryString
-----------------

:Type: Boolean
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, query string parameters and the respective response will be logged.

query.rawPost
-------------

:Type: Boolean
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, POST requests against the Solr server will be logged.

query.rawGet
------------

:Type: Boolean
:Default: 1
:Options: 0,1
:Since: 1.0

If enabled, GET requests against the Solr server will be logged.