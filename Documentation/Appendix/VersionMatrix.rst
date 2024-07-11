.. include:: /Includes.rst.txt
.. _appendix-version-matrix:

Appendix - Version Matrix
=========================

Supported versions
------------------

List of EXT:solr versions and the matching versions of Apache Solr and TYPO3 that are supported:

========= ========== ========== =========== =============== ================== ================================ =============== =============== =================
       Basic components                Funding contribution extensions          Published funding contribution    Extensions         Solr configuration
------------------------------- ---------------------------------------------- -------------------------------- --------------- ---------------------------------
TYPO3     EXT:solr   EXT:tika   EXT:solrfal EXT:solrconsole EXT:solrdebugtools EXT:solrfluidgrouping            EXT:solrmlt     Apache Solr     Configset
========= ========== ========== =========== =============== ================== ================================ =============== =============== =================
12.4      12.0       12.0       12.0        12.0            12.0               N/A (integrated in EXT:solr)     12.0 (Ø)        9.6.1¹          ext_solr_12_0_0
11.5      11.5       11.0       11.0        11.0            11.0               11.0                             11.0 (Ø)        8.11.3¹         ext_solr_11_5_0
========= ========== ========== =========== =============== ================== ================================ =============== =============== =================

| Ø  - not yet available
| ᾱ  - non stable alpha release
| β  - non stable beta release
| rc - release candidate available
| ¹  - recommended Apache Solr version, check version matrix in composer.json (`composer info:solr-versions`) for full list

.. important::

    | Non-stable releases are not available in TER, but
    | via Composer or as a ZIP file attachment on GitHub `release <https://github.com/TYPO3-Solr/ext-solr/releases>`_ page.


Extended Long Term Support (ELTS)
---------------------------------

Since January 2022, we have been following the TYPO3 release cycles and actively support the last two TYPO3 versions; in addition, we offer ELTS support for
selected older versions. The following table illustrates the offers and available and upcoming versions:

========= =========== ========== =========== =============== ================== =============== ====================
       Basic components                 Funding contribution extensions                 Solr configuration
-------------------------------- ---------------------------------------------- ------------------------------------
TYPO3     EXT:solr    EXT:tika   EXT:solrfal EXT:solrconsole EXT:solrdebugtools Apache Solr     Configset
========= =========== ========== =========== =============== ================== =============== ====================
10.4      11.2.4+     10.0       10.0        10.0            10.0               9.5.0¹          ext_solr_11_2_0_elts
9.5-10.4  11.0.9+     6.0.3+     8.0.2+      4.0.2+          1.1.3+             9.5.0¹          ext_solr_11_0_0_elts
========= =========== ========== =========== =============== ================== =============== ====================

Our Apache Solr for TYPO3 EB-partners newsletter will keep you updated!

| Ø - not yet available
| ¹ - recommended Apache Solr version, check version matrix in composer.json (`composer info:solr-versions`) for full list

No longer supported versions
----------------------------

========= ========== ========= =========== =============== ================== =========== =========== ================
TYPO3     EXT:solr   EXT:tika  EXT:solrfal EXT:solrconsole EXT:solrdebugtools EXT:solrmlt Apache Solr Configset
========= ========== ========= =========== =============== ================== =========== =========== ================
10.4      11.2.0-3   10.0      10.0        10.0            10.0               10.0        8.11        ext_solr_11_2_0
10.4      11.1       10.0      10.0        10.0            10.0               10.0        8.9         ext_solr_11_1_0
9.5-10.4  11.0.0-7   6.0.0-2   8.0.0-1     4.0.0-1         1.1.2              3.1         8.5         ext_solr_11_0_0
9.5       10.0       5.0       7.0         3.0             1.1.1              3.0         8.2         ext_solr_10_0_0
8.7-9.5   9.0        4.0       6.0         2.0             1.1.0              3.0         7.6         ext_solr_9_0_0
8.7       8.1        3.1       5.1         1.0             1.0.0              2.0         6.6         ext_solr_8_1_0
8.7       8.0        3.0       5.0         N/A             N/A                N/A         6.6         ext_solr_8_0_0
8.7       7.5        2.4       4.2         N/A             N/A                N/A         6.6         ext_solr_7_5_0
8.7       7.0        2.4       4.2         N/A             N/A                N/A         6.3         ext_solr_7_0_0
========= ========== ========= =========== =============== ================== =========== =========== ================

Obsolete versions
-----------------

========== ========= ========= =========== ============= ================ ================ =========== ======================== ======================== ============
TYPO3      EXT:solr  EXT:tika  EXT:solrfal EXT:solrfluid EXT:solrgrouping EXT:solrmlt      Apache Solr Schema                   Solrconfig               Accessplugin
========== ========= ========= =========== ============= ================ ================ =========== ======================== ======================== ============
7.6 - 8.x  6.5       2.3       4.1         2.0           1.3              N/A              6.6.2       tx_solr-6-5-0--20171023  tx_solr-6-5-0--20171023  2.0
7.6 - 8.7  6.1       2.3       4.1         2.0           1.3              N/A              6.3         tx_solr-6-1-0--20170206  tx_solr-6-1-0--20161220  2.0
7.6        6.0       2.2       4.0         1.2           1.3              N/A              6.3         tx_solr-6-0-0--20161209  tx_solr-6-0-0--20161122  1.7
7.6        5.1       2.1       3.2         1.2           1.3              N/A              4.10        tx_solr-5-1-0--20160725  tx_solr-4-0-0--20160406  1.3
7.6        5.0       2.1       3.1         1.0           1.3              N/A              4.10        tx_solr-4-0-0--20160406  tx_solr-4-0-0--20160406  1.3
7.6        4.0       2.1       3.0         N/A           1.2              N/A              4.10        tx_solr-4-0-0--20160406  tx_solr-4-0-0--20160406  1.3
6.2 - 7.6  3.1       2.0       2.1         N/A           1.1              1.1              4.10        tx_solr-3-1-0--20150614  tx_solr-3-1-0--20151012  1.3
========== ========= ========= =========== ============= ================ ================ =========== ======================== ======================== ============
