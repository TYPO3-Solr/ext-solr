.. _appendix-version-matrix:

Appendix - Version Matrix
=========================

.. seealso::

    You are on docs for EXT:solr |release| version, please refer to `Version Matrix on main release <https://docs.typo3.org/p/apache-solr-for-typo3/solr/main/en-us/Releases/Index.html>`_ to see all versions.

Requirements for EXT:solr* 11.6 stack
-------------------------------------

========= ============= ============= ================= ====================  =======================  ================================  ===============  =====================
TYPO3     EXT:solr (↻)  EXT:tika (↻)  EXT:solrfal ($)   EXT:solrconsole ($)   EXT:solrdebugtools ($)   EXT:solrfluidgrouping ($↺)        Apache Solr      Configset
========= ============= ============= ================= ====================  =======================  ================================  ===============  =====================
11.5      11.6          11.0          11.0              11.0                  11.0                     11.0                              9.10.1¹          ext_solr_11_6_0_elts
========= ============= ============= ================= ====================  =======================  ================================  ===============  =====================

| $ - Funding contribution extensions. See: https://www.typo3-solr.com/solr-for-typo3/open-source-version/
| $↺ - Published funding contribution. Previously as ($), but merged in EXT:solr core
| ↻ - Open Source and financed by ($)
| Ø  - not yet available
| ᾱ  - non stable alpha release
| β  - non stable beta release
| rc - release candidate available
| ¹  - recommended Apache Solr version, check version matrix in composer.json (`composer info:solr-versions`) for full list