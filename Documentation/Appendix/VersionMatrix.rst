.. include:: /Includes.rst.txt
.. _appendix-version-matrix:

Appendix - Version Matrix
=========================

.. seealso::

    You are on docs for EXT:solr |release| version, please refer to `Version Matrix on main release <https://docs.typo3.org/p/apache-solr-for-typo3/solr/main/en-us/Releases/Index.html>`_ to see all versions.


   See also EXT:solr v11.6 for TYPO3 11.5 LTS.


Requirements for EXT:solr* 11.6 stack
-------------------------------------

========= ========== ========== =========== =============== ================== ============================= =============== =============== =================
       Basic components                Funding contribution extensions          Published funding contribution extensions         Solr configuration
------------------------------- ---------------------------------------------- --------------------------------------------- ---------------------------------
TYPO3     EXT: solr  EXT:tika   EXT:solrfal EXT:solrconsole EXT:solrdebugtools EXT:solrfluidgrouping         EXT:solrmlt     Apache Solr     Configset
========= ========== ========== =========== =============== ================== ============================= =============== =============== =================
11.5      11.6       11.0       11.0        11.0            11.0               11.0                          11.0 (Ø)        9.3.0¹         ext_solr_11_6_0
========= ========== ========== =========== =============== ================== ============================= =============== =============== =================

| ¹ - recommended Apache Solr version, check version matrix in composer.json (`composer info:solr-versions`) for full list