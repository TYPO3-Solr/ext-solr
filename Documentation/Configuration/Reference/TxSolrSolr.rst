.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _conf-tx-solr-solr:

tx_solr.solr
============

.. warning::

   The ability to use the legacy mode was removed in EXT:solr 11.0. please configure your solr connections together with your TYPO3 site in the site module

This section defines the possible configurations for Apache Solr connection.


.. contents::
   :local:

timeout
-------

:Type: Float
:TS Path: plugin.tx_solr.solr.timeout
:Default: 0.0
:Since: 1.0
:cObject supported: no
:Deprecated: 10.0

Can be used to configure a connection timeout.
