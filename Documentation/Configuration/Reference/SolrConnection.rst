.. include:: /Includes.rst.txt


.. _conf-solr-client:

Solr Connection
===============

EXT:solr uses the TYPO3 PSR-7 client to connect to the Solr server.

You can apply additional configuration for proxy settings using `$GLOBALS['TYPO3_CONF_VARS']['HTTP']`.

.. tip::
   For more details about Guzzle settings and TYPO3 implementation see


   - https://docs.guzzlephp.org/en/latest/request-options.html
   - https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/ApiOverview/Http/Index.html
