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


**When i open the search page i see the message 'Search is currently not available. ', whats wrong?**

Did you configure your solr connection as required?

- Please read ":ref:started-configure-extension`" and check if you have configured everything
- Did you configure solr server and port and does the scheme and path match?
- Did you click "Initialize connection" after configuring the solr server?
- Can you access the solr server with wget or curl from the command line?
- Is the system report of EXT:solr green?

|

**I want to index files with EXT:solr. How can i do that?**

We provide an addon called EXT:solrfal, that allows you to index files from FAL into Solr. This addon is currently available for partner only.

|

**How can i use fluid templates with EXT:solr?**

For the fluid rendering we provide the addon EXT:solrfluid, that allows you to render your search results with fluid.

|

**Which versions of EXT:solr / EXT:solrfal and EXT:solrfluid work together?**

Please check the :ref:`appendix-version-matrix`, the you can find the proposed version combinations.

|

**My indexed documents are empty, i can not find the content of a page?**

Did you configure the search markers(`<!-- TYPO3SEARCH_begin -->` and `<!-- TYPO3SEARCH_end -->`) on your page? Check the paragraph :ref:`Search Markers` and make sure your page renders them.

|

**I have languages in TYPO3 that are not relevant for the search. How can i exclude them?**

You need to enable the search just for the relevant languages.

Example:

|

.. code-block:: typoscript

    plugin.tx_solr.enabled = 0

    [globalVar = GP:L = 0]
        plugin.tx_solr {
            enabled = 1
            solr.path = /solr/core_de/
        }
    [globalVar = GP:L = 8|9]
        plugin.tx_solr {
            enabled = 1
            solr.path = /solr/core_en/
        }
    [END]

|

**When i change a record, no update is detected. What's wrong?**

Are your records inside of your site root? EXT:solr record monitor processes records that belong to your site, which means they need to be below your site root.
If you want to index records that are outside your sideroot, you need to configure the page id's of the sysfolder as additionalPageIds:


|

.. code-block:: typoscript
    plugin.tx_solr.index.queue.[yourQueueName].additionalPageIds = 4711,4712

|

**There are two datatypes for text stringS and textS. When should i choose which datatype?**

String data types like stringS store the *raw* string. No processing, like stemming, splitting etc. is applied. The processing is useful when you want to search in the field and support more then exact matches. When you just want to display the content you should choose a *stringS* type, when you want to search in the field you should choose *textS*.

|

**I am adding content to a dynamic field but when i search for the content i can not find the document. What's wrong?**

Beside the indexing part you need to configure the query part. Make sure that all relevant fields are configured as query fields:


|

.. code-block:: typoscript

    plugin.tx_solr.search.query.queryFields := addToList(test_textS^1.0)

|

**I don't find the expected document on the first position. What can i do?**

:) That's a good question. In the end, solr is a search and the sorting depends on the score, not as in a database on one or two simple criterion.

In the end solr provides a lot of settings that influence the score calculation and you need to tune the results to you needs. The following settings are helpful to tune your results.

*Check your data*

The quality of you data is important. Maybe a document is on the first position because, the search term is really relevant for it? Maybe it is an option to change the content?

*Adjust the query field boost factors*

For each query field there is a boost value after the ^ sign. To increase the factor of a single field for the whole query, you can increase the number in the query fields.

Example:

|

.. code-block:: typoscript

    plugin.tx_solr.search.query.queryFields = title^20.0, title^15.0

*Use boostFunctions or boostQueries*

For use cases like "*news* are allways more important then *pages*" or "Newer documents should be at the beginning" you can use boostFunctions (:ref:`conf-tx-solr-search-boostFunction`) or boostQueries (:ref:`conf-tx-solr-search-boostQuery`)

*The search term only exists as a synonym*

You can use the backend module synonyms (:ref:`Synonyms`) to maintain synonyms and configure solr to retrieve documents by a term that is not naturally inside the document.

*Ask DKD support*

Beside that, there are more options to tune. The DKD support can help you, to analyze and tune your search results. Call +49 (0)69 - 247 52 18-0.