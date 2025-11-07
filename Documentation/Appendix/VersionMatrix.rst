.. _appendix-version-matrix:

Appendix - Version Matrix
=========================

.. seealso::

    You are on docs for EXT:solr |release| version, please refer to `Version Matrix on main release <https://docs.typo3.org/p/apache-solr-for-typo3/solr/main/en-us/Appendix/VersionMatrix.html>`_ to see all versions.

Supported versions
------------------

List of EXT:solr versions and the matching versions of Apache Solr and TYPO3 that are supported:

=========  =============  ================  =============  =================  ====================  =======================  ================================  ===============  =================
TYPO3      EXT:solr (↻)   EXT:solrmlt (↻)   EXT:tika (↻)   EXT:solrfal ($)    EXT:solrconsole ($)   EXT:solrdebugtools ($)   EXT:solrfluidgrouping ($↺)        Apache Solr      Configset
=========  =============  ================  =============  =================  ====================  =======================  ================================  ===============  =================
12.4       12.1           12.0 (Ø)          12.0           12.0               12.0                  12.0                     N/A (integrated in EXT:solr)      9.10.0¹           ext_solr_12_1_0
=========  =============  ================  =============  =================  ====================  =======================  ================================  ===============  =================

| $ - Funding contribution extensions. See: https://www.typo3-solr.com/solr-for-typo3/open-source-version/
| $↺ - Published funding contribution. Previously as ($), but merged in EXT:solr core
| ↻ - Open Source and financed by ($)
| Ø  - not yet available
| ¹  - recommended Apache Solr version, check version matrix in composer.json (`composer info:solr-versions`) for full list
