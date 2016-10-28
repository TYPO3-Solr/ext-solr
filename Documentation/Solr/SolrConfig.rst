=====================
SolrConfig Parameters
=====================

There are several parameters in the solrconfig.xml that can be used to tune your solr server.
Our solrconfig.xml is designed to ship a reasonable configuration for the most standard use cases.

For use cases with very large indexes or high performance requirements it makes sence to tune those parameters

indexConfig.useCompoundFile
===========================

This value is "true" by default in our configuration. By setting this value to true solr only writes one file
for indexes instead of many. This is a little bit slower but more robust to prevent errors with "Too many open files".
