.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _conf-tx-solr:

tx_solr
=======

This section defines general configuration options.

.. contents::
   :local:


enabled
-------

:Type: Boolean
:Default: ``1``
:Options: 0, 1
:Since: 1.2

A switch to completely turn on / off EXT:solr. Comes in handy with multi site
installations where you want to enable EXT:solr only for certain sites, but
still have the extension's configuration at a single place and include that for
each site. Just set enabled = 0 for each site's root TS template or use
conditions where you do not want EXT:solr.

.. important::

   This also influences the connection manager; connections will be registered /
   detected only for enabled = 1.

addDefaultCss
-------------

:Type: Boolean
:Default: ``1``
:Options: 0, 1
:Since: 1.0
:Removed: 2.0, see tx_solr.cssFiles instead

If enabled, default stylesheets for page browser, results, and suggestions will
be included. Otherwise, no stylesheets will be included by EXT:solr.

addDefaultJs
------------

:Type: Boolean
:Default: ``1``
:Options: 0, 1
:Since: 1.0
:Removed: 2.0, see tx_solr.javascriptFiles instead

If enabled, some javascript files for facet option (un)folding and suggestions
will be included. Furthermore, the jQuery UI library required for suggestions
can only get included, if addDefaultJs is set.

enableDebugMode
---------------

:Type: Boolean
:Default: ``0``
:Options: 0, 1
:Since: 1.0
:See: http://wiki.apache.org/solr/CommonQueryParameters#debugQuery

If enabled, the debugQuery query parameter is added to the Solr queries. Solr
will then return additional information explaining the the query, scoring,
timing, and other information.

