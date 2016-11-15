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


**The extension is indexing into the wrong core for multi-language sites. What's wrong?**

When indexing pages the page indexer retrieves the core from the TypoScript configuration. That configuration is determined by the language (GET L parameter). However, when the indexer tries to index a page that has not been translated TYPO3 will by default still render the page but falling back to the default language page. By that TYPO3 will also use the TypoScript configuration for the default language which usually points to a different Solr core.

Solution: Make sure you have configured config.sys_language_mode to use content_fallback. This way TYPO3 will fall back to the configured language's content, but will use the TypoScript configuration for the requested language.

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

For use cases like "*news* are always more important then *pages*" or "Newer documents should be at the beginning" you can use boostFunctions (:ref:`conf-tx-solr-search-boostFunction`) or boostQueries (:ref:`conf-tx-solr-search-boostQuery`)

*The search term only exists as a synonym*

You can use the backend module synonyms (:ref:`Synonyms`) to maintain synonyms and configure solr to retrieve documents by a term that is not naturally inside the document.

*Ask DKD support*

Beside that, there are more options to tune. The DKD support can help you, to analyze and tune your search results. Call +49 (0)69 - 247 52 18-0.

**Non ASCII characters like german umlauts do not work when i search, how do I fix that?**

To allow search with umlauts Tomcat needs to be configured to use UTF-8 encoded urls. Go to apache-tomcat/conf/server.xml and change the URIEncoding parameter:


|

.. code-block:: xml

    <Connector port="8080" protocol="HTTP/1.1"
        connectionTimeout="20000" redirectPort="8443"
        URIEncoding="UTF-8" />

**How can I change Solr's schema and add custom fields?**

Please do not change the shipped solr schema. There are a lot of dynamic fields (:ref:`appendix-dynamic-fields`) that can be used to index any kind of datatype.

**I am using varnish before my site. How can i index pages properly?**

SOLR Indexer might have some issues, when the page to index is behind a Varnish Proxy. We have collected two ways of solving this issue

*Bypassing varnish:*

Bypass when X-Tx-Solr-Iq is present

The SOLR indexer request send the header X-Tx-Solr-Iq.

To have bypass the Varnish caching, put this into your sub vcl_recv part of the configuration


|

::

    if (req.http.X-Tx-Solr-Iq) {
        return(pipe);
    }


*Using Cache-Control:*

Put this into your sub vcl_fetch part of the configuration

|

::

    if (req.http.Cache-Control ~ "no-cache") {
        set beresp.ttl = 0s;
        # Make sure ESI includes are processed!
        esi;
        set beresp.http.X-Cacheable = "NO:force-reload";
        # Make sure that We remove all cache headers, so the Browser does not cache it for us!
        remove beresp.http.Cache-Control;
        remove beresp.http.Expires;
        remove beresp.http.Last-Modified;
        remove beresp.http.ETag;
        remove beresp.http.Pragma;

          return (deliver);
    }


**I want to build the Dockerfile_full image on my mac with a local volume, how can i do that?

|

The following example shows how to build the Dockerfile_full image and start a container with a mapped local volume.
This was tested with "Docker for Mac" (not Docker Toolbox)

::

    # build the image
    docker build -t solr-full -f Dockerfile_full .

    # create volume directory locally
    mkdir -p ~/solrdata

    # add solr group to volume directory
    sudo chown :8983 ~/solrdata

    # run docker container from image with volume
    docker run -d -p 127.0.0.1:8282:8983 -v ~/solrdata:/opt/solr/server/solr/data solr-full


** Can i index a https (SSL) site?

Yes. You need a valid ssl certificate and change the following setting:

::

    plugin.tx_solr.index.queue.pages.frontendDataHelper.scheme = https

|



