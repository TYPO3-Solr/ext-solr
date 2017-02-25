.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. raw:: latex

    \newpage

.. raw:: pdf

   PageBreak

.. _conf-tx-solr-settings:

Extension Configuration
=======================

The following settings can be defined in the extension manager

useConfigurationFromClosestTemplate
-----------------------------------

:Type: Boolean
:Since: 6.1
:Default: 0

When this setting is active the closest page with a typoscript template will be used to fetch the configuration.
This improves the performance but limits also the possibilities. E.g. conditions can not be used that are related to a certain page.
