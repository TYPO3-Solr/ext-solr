.. _appendix-version-matrix:

Appendix - Version Matrix
=========================

Supported versions
------------------

List of EXT:solr versions and the matching versions of Apache Solr and TYPO3 that are supported:

=========  =============  =============  =================  ====================  =======================  ===============  =================
TYPO3      EXT:solr (↻)   EXT:tika (↻)   EXT:solrfal ($)    EXT:solrconsole ($)   EXT:solrdebugtools ($)   Apache Solr      Configset
=========  =============  =============  =================  ====================  =======================  ===============  =================
14.3       14.0           14.0           14.0               14.0.0                14.0.0                   10.0.0¹           ext_solr_14_0_0
13.4       13.1           13.1           13.0               13.0                  13.0                     9.10.1¹           ext_solr_13_1_0
=========  =============  =============  =================  ====================  =======================  ===============  =================

| $ - Funding contribution extensions. See: https://www.typo3-solr.com/solr-for-typo3/open-source-version/
| $↺ - Published funding contribution. Previously as ($), but merged in EXT:solr core
| ↻ - Open Source and financed by ($)
| Ø  - not yet available
| ᾱ  - non stable alpha release
| β  - non stable beta release
| rc - release candidate available
| ¹  - recommended Apache Solr version, check version matrix in composer.json (`composer info:solr-versions`) for full list

.. important::

    | Non-stable releases are not available in TER, but
    | via Composer or as a ZIP file attachment on GitHub `release <https://github.com/TYPO3-Solr/ext-solr/releases>`_ page.


No longer supported versions
----------------------------

..  note::
    On September 16, 2026, we released the following versions one last time, addressing the security vulnerabilities of 2026:

    *   11.2.8 (TYPO3 10 ELTS)
    *   11.6.7 (TYPO3 11 ELTS)
    *   12.1.5 (TYPO3 12 ELTS)


=========  ==========  =========  ===========  ===============  ==================  ===========  ===========  ======================================
TYPO3      EXT:solr    EXT:tika   EXT:solrfal  EXT:solrconsole  EXT:solrdebugtools  EXT:solrmlt  Apache Solr  Configset
=========  ==========  =========  ===========  ===============  ==================  ===========  ===========  ======================================
13.4       13.0        -          -            -                -                   -            9.10.1       ext_solr_13_0_0
12.4       12.0-1      -          -            -                -                   -            9.10.1       ext_solr_12_0_0, ext_solr_12_1_0
11.5       11.6        11.0       11.0         11.0             11.0                N/A          9.10.1       ext_solr_11_6_0, ext_solr_11_6_0_elts
11.5       11.5        11.0       11.0         11.0             11.0                N/A          8.11         ext_solr_11_5_0
10.4       11.2        10.0       10.0         10.0             10.0                10.0         8.11         ext_solr_11_2_0, ext_solr_11_2_0_elts
10.4       11.1        10.0       10.0         10.0             10.0                10.0         8.9          ext_solr_11_1_0
9.5-10.4   11.0.0-9    6.0.0-3    8.0.0-1      4.0.0-1          1.1.2               3.1          8.5          ext_solr_11_0_0, ext_solr_11_0_0_elts
=========  ==========  =========  ===========  ===============  ==================  ===========  ===========  ======================================

Obsolete versions
-----------------

==========  =========  =========  ===========  ===============  =====================  ==================  =============  ================  ===========  ===========  ========================  =========================  ============
TYPO3       EXT:solr   EXT:tika   EXT:solrfal  EXT:solrconsole  EXT:solrfluidgrouping  EXT:solrdebugtools  EXT:solrfluid  EXT:solrgrouping  EXT:solrmlt  Apache Solr  Schema                    Solrconfig                 Accessplugin
==========  =========  =========  ===========  ===============  =====================  ==================  =============  ================  ===========  ===========  ========================  =========================  ============
9.5         10.0       5.0        7.0          4.0              2.0                    1.1.2               N/A            N/A               3.0          8.2.0        tx_solr-10-0-0--20191010  tx_solr-10-0-0--20191010   4.0
8.7 - 9.5    9.0       4.0        6.0          2.0              2.0                    1.0.0-1.1.1         N/A            N/A               3.0          7.6.0        tx_solr-9-0-0--20180727   tx_solr-9-0-0--20180727    3.0
8.7          8.1       3.1        5.1          1.0              1.1                    1.0.0-1.1.1         N/A            N/A               2.0          6.6.3        tx_solr-8-1-0--20180615   tx_solr-8-1-0--20180615    2.0
8.7          8.0       3.0        5.0          N/A              1.0                    1.0.0-1.1.1         N/A            N/A               N/A          6.6.2        tx_solr-8-0-0--20171020   tx_solr-8-0-0--20171020    2.0
8.7          7.5       2.4        4.2          N/A              N/A                    1.0.0-1.1.1         N/A            N/A               N/A          6.6.2        tx_solr-7-5-0--20171023   tx_solr-7-5-0--20171023    2.0
8.7          7.0       2.4        4.2          N/A              N/A                    1.0.0-1.1.1         N/A            N/A               N/A          6.3          tx_solr-7-0-0--20170530   tx_solr-7-0-0--20170530    2.0
7.6 - 8.x    6.5       2.3        4.1          N/A              N/A                    N/A                 2.0            1.3               N/A          6.6.2        tx_solr-6-5-0--20171023   tx_solr-6-5-0--20171023    2.0
7.6 - 8.7    6.1       2.3        4.1          N/A              N/A                    N/A                 2.0            1.3               N/A          6.3          tx_solr-6-1-0--20170206   tx_solr-6-1-0--20161220    2.0
7.6          6.0       2.2        4.0          N/A              N/A                    N/A                 1.2            1.3               N/A          6.3          tx_solr-6-0-0--20161209   tx_solr-6-0-0--20161122    1.7
7.6          5.1       2.1        3.2          N/A              N/A                    N/A                 1.2            1.3               N/A          4.10         tx_solr-5-1-0--20160725   tx_solr-4-0-0--20160406    1.3
7.6          5.0       2.1        3.1          N/A              N/A                    N/A                 1.0            1.3               N/A          4.10         tx_solr-4-0-0--20160406   tx_solr-4-0-0--20160406    1.3
7.6          4.0       2.1        3.0          N/A              N/A                    N/A                 N/A            1.2               N/A          4.10         tx_solr-4-0-0--20160406   tx_solr-4-0-0--20160406    1.3
6.2 - 7.6    3.1       2.0        2.1          N/A              N/A                    N/A                 N/A            1.1               1.1          4.10         tx_solr-3-1-0--20150614   tx_solr-3-1-0--20151012    1.3
==========  =========  =========  ===========  ===============  =====================  ==================  =============  ================  ===========  ===========  ========================  =========================  ============
