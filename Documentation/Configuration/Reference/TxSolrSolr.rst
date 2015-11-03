.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


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
:Default: http
:Options: http, https
:Since: 1.2 2.0

Allows to set the connection scheme to "https" instead of the default "http".

host
----

:Type: String
:Default: localhost
:Since: 1.0

Sets the host portion of the URL.

port
----

:Type: Integer
:Default: 8080
:Since: 1.0

Sets the port portion of the URL.

path
----

:Type: String
:Default: /
:Since: 1.0

Sets the path portion of the URL. Make sure to have the path end with a slash (/).

