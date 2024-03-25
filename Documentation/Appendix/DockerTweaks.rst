.. include:: /Includes.rst.txt


.. _appendix-docker-tweaks:

Appendix - Docker Tweaks
========================

Beside of original Apache Solr Docker image, on which is our image based on we provide some tweaks to make our and your work simpler.

Disable unnecessary cores on container start
--------------------------------------------

By defining env ``TYPO3_SOLR_ENABLED_CORES`` with a space separated list of languages/cores to enable, only those cores will be initialized on start-up.
This allows to save memory usage of Apache Solr server instance.

Usage:

.. code-block::  bash

    docker run -e 'TYPO3_SOLR_ENABLED_CORES=english german' -it typo3solr/ext-solr
