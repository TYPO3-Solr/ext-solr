.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _conf-tx-solr-general:

tx_solr.general
===============

This section defines general settings.

.. contents::
   :local:


dateFormat.date
---------------

:Type: String
:TS Path: plugin.tx_solr.general.dateFormat.date
:Default: d.m.Y H:i
:Since: 1.0
:See: http://www.php.net/manual/de/function.strftime.php

Defines the format that is used for dates throughout the extension like in
view helpers for example. The format uses the `strftime()` php function syntax,
please consult the php documentation for available options.

