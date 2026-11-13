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

Run Solr in SolrCloud mode
--------------------------

By default the container runs Solr in user-managed (standalone) mode. With ``SOLR_MODE=cloud`` it starts a single
SolrCloud node with embedded ZooKeeper instead. The enabled cores are created as collections with the same name,
config set and schema, so EXT:solr can connect to them the same way as to the cores in user-managed mode.

This is meant for development and testing. For production, set up a SolrCloud cluster with an external ZooKeeper
ensemble as described in the Apache Solr documentation.

Usage:

.. code-block::  bash

    docker run -e 'SOLR_MODE=cloud' -e 'TYPO3_SOLR_ENABLED_CORES=english german' -it typo3solr/ext-solr
