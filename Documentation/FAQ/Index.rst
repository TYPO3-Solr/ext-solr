.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _conf-index:


.. raw:: latex

    \newpage

.. raw:: pdf

   PageBreak

FAQ - Frequently Asked Questions
================================


**When i open the search page i see the message 'Search is currently not available. ', whats wrong?***

Did you configure your solr connection as required?

- Please read ":ref:started-configure-extension`" and check if you have configured everything
- Did you configure solr server and port and does the scheme and path match?
- Did you click "Initialize connection" after configuring the solr server?
- Can you access the solr server with wget or curl from the command line?
- Is the system report of EXT:solr green?

**I want to index files with EXT:solr. How can i do that?**

We provide an addon called EXT:solrfal, that allows you to index files from FAL into Solr. This addon is currently available for partner only.

**How can i use fluid templates with EXT:solr?**

For the fluid rendering we provide the addon EXT:solrfluid, that allows you to render your search results with fluid.

**Which versions of EXT:solr / EXT:solrfal and EXT:solrfluid work together?**

Please check the :ref:`appendix-version-matrix`, the you can find the proposed version combinations.
