.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _conf-tx-solr-settings:

Extension Configuration
=======================

The following settings can be defined in the "Settings":>"Extension Configuration":>"solr"

useConfigurationFromClosestTemplate
-----------------------------------

:Type: Boolean
:Since: 6.1
:Default: 0

When this setting is active the closest page with a typoscript template will be used to fetch the configuration.
This improves the performance but limits also the possibilities. E.g. conditions can not be used that are related to a certain page.

useConfigurationTrackRecordsOutsideSiteroot
-------------------------------------------

:Type: Boolean
:Since: 6.1
:Default: 1

A common common scenario is to have a site and a storage folder for records parallel to it
on the same level (f.e.)
If you don't want this behaviour - it should be set to false.

allowSelfSignedCertificates
---------------------------

:Type: Boolean
:Since: 6.1
:Default: 0

Can be used to allow self signed certificates - when using the SSL protocol.


allowLegacySiteMode
-------------------

:Type: Boolean
:Since: 10.0
:Default: 0

Can be used to allow using classic TypoScript Configuration for Sites.
