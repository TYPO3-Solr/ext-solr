.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. raw:: latex

    \newpage

.. raw:: pdf

   PageBreak

.. _conf-tx-solr-solr:

tx_solr.solr
============

This section defines the address of the Solr server. As the communication with
the Solr server happens over HTTP this is just a simple URL. Each of the URL's
components can be defined separately.


.. contents::
   :local:


scheme
------

:Type: String
:TS Path: plugin.tx_solr.solr.scheme
:Default: http
:Options: http, https
:Since: 1.2 2.0

Allows to set the connection scheme to "https" instead of the default "http".

host
----

:Type: String
:TS Path: plugin.tx_solr.solr.host
:Default: localhost
:Since: 1.0

Sets the host portion of the URL.

port
----

:Type: Integer
:TS Path: plugin.tx_solr.solr.port
:Default: 8983
:Since: 1.0

Sets the port portion of the URL.

path
----

:Type: String
:TS Path: plugin.tx_solr.solr.path
:Default: /
:Since: 1.0

Sets the path portion of the URL. Make sure to have the path end with a slash (/).

username
--------

:Type: String
:TS Path: plugin.tx_solr.solr.username
:Default: /
:Since: 6.0

   Sets the username required to access the solr server.

password
--------

:Type: String
:TS Path: plugin.tx_solr.solr.password
:Default: /
:Since: 6.0

   Sets the username required to access the solr server.

