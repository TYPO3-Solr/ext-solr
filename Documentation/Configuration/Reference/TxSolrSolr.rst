.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _conf-tx-solr-solr:

tx_solr.solr
============

.. warning::

   The ability to use the legacy mode will be removed in EXT:solr 11.0. please configure your solr connections together with your TYPO3 site in the site module

This section defines the address of the Solr server. As the communication with
the Solr server happens over HTTP this is just a simple URL. Each of the URL's
components can be defined separately.

Note: Since version 9 of EXT:solr you can optionally configure different endpoints for reading and writing

.. code-block:: typoscript

   plugin.tx_solr.solr {
      read {
         scheme = https
         host = localhost
         port = 8983
         path = /solr/core_en/
      }
      write < .read
   }

As fallback (when nothing special is configured in read or write, EXT:solr is fallingback to the old global setting)

Example:

* When nothing is configured in ```plugin.tx_solr.solr.read.host``` the path ```plugin.tx_solr.solr.host``` is used.


.. contents::
   :local:


scheme
------

:Type: String
:TS Path: plugin.tx_solr.solr.scheme
:Default: http
:Options: http, https
:cObject supported: yes
:Since: 1.2 2.0
:Deprecated: 10.0

Allows to set the connection scheme to "https" instead of the default "http".

host
----

:Type: String
:TS Path: plugin.tx_solr.solr.host
:Default: localhost
:cObject supported: yes
:Since: 1.0
:Deprecated: 10.0

Sets the host portion of the URL.

port
----

:Type: Integer
:TS Path: plugin.tx_solr.solr.port
:Default: 8983
:cObject supported: yes
:Since: 1.0
:Deprecated: 10.0

Sets the port portion of the URL.

path
----

:Type: String
:TS Path: plugin.tx_solr.solr.path
:Default: /
:cObject supported: yes
:Since: 1.0
:Deprecated: 10.0

Sets the path portion of the URL. Make sure to have the path end with a slash (/).

username
--------

:Type: String
:TS Path: plugin.tx_solr.solr.username
:Since: 6.0
:cObject supported: yes
:Deprecated: 10.0

Sets the username required to access the solr server.

password
--------

:Type: String
:TS Path: plugin.tx_solr.solr.password
:Since: 6.0
:cObject supported: yes
:Deprecated: 10.0

Sets the password required to access the solr server.

timeout
-------

:Type: Float
:TS Path: plugin.tx_solr.solr.timeout
:Default: 0.0
:Since: 1.0
:cObject supported: no
:Deprecated: 10.0

Can be used to configure a connection timeout.
