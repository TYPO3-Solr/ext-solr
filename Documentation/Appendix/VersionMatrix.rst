.. include:: /Includes.rst.txt
.. _appendix-version-matrix:

Appendix - Version Matrix
=========================

.. seealso::

    You are on docs for EXT:solr |release| version, please refer to `Version Matrix on main release <https://docs.typo3.org/p/apache-solr-for-typo3/solr/main/en-us/Releases/Index.html>`_ to see all versions.

.. tip::

   There is EXT:solr v11.6 for TYPO3 11.5 LTS.


Requirements for EXT:solr* 11.5 stack
-------------------------------------

========= ========== ========== =========== =============== ================== ============================= =============== =============== =================
       Basic components                Funding contribution extensions          Published funding contribution extensions         Solr configuration
------------------------------- ---------------------------------------------- --------------------------------------------- ---------------------------------
TYPO3     EXT: solr  EXT:tika   EXT:solrfal EXT:solrconsole EXT:solrdebugtools EXT:solrfluidgrouping         EXT:solrmlt     Apache Solr     Configset
========= ========== ========== =========== =============== ================== ============================= =============== =============== =================
11.5      11.5       11.0       11.0        11.0            11.0               11.0                          11.0 (Ø)        8.11.3¹         ext_solr_11_5_0
========= ========== ========== =========== =============== ================== ============================= =============== =============== =================

|¹ - recommended Apache Solr version, check version matrix in composer.json (composer info:solr-versions) for full list

..  warning::
   Apache Solr 8.11.3 contains a breaking change, see security fix "SOLR-14853: Make enableRemoteStreaming option global; not configSet". EXT:solr relies on stream bodies
   which aren't enabled by default since 8.11.3. EXT:solr 11.5.6 contains all required settings, but if you're updating and not using our Docker image, you have to
   set "enableRemoteStreaming=true" and "solr.enableStreamBody=true". TYPO3 reports module will print a warning if you have to reconfigure.
