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


**What does the term `"Core"<https://cwiki.apache.org/confluence/display/solr/Solr+Cores+and+solr.xml>`_  mean?**

This term relates to Apache Solr indexes and means a single distinct part of an index. It is possible to use multiple cores on one single Apache Solr instance.
Good examples are using a different Apache Solr core for each language or of course a separate core for each website.
For more informations please refer to the Apache Solr documentation.

|

**Where can I report a bug?**

Please make sure that this bug is not reported already, use also the search function of our issue tracker.
Our issue tracker is on `GitHub <https://github.com/TYPO3-Solr/ext-solr/issues/>`_.

|

**Where can I report a security issue?**

If you have found a security issue in our extension, please do not post about it in a public channel.
Please `send us an email <mailto:team-solr@dkd.de>`_ with detailed description of found vulnerability.

|

**Is there some chat/irc channel for EXT:solr available?**

Join us on the official `Slack for TYPO3 <https://forger.typo3.org/slack>`_ and get answers related to EXT:solr in the #ext-solr channel immediately!

|

**When i open the search page i see the message 'Search is currently not available. ', whats wrong?**

Did you configure your solr connection as required?

- Please read ":ref:`started-configure-extension`" and check if you have configured everything
- Did you configure solr server and port and does the scheme and path match?
- Did you click "Initialize connection" after configuring the solr server?
- Can you access the solr server with wget or curl from the command line?
- Is the system report of EXT:solr green?

|

**In which cases do I want to trigger indexing manually?**

- after changing any configuration file.
- after modifying synonyms, stop words, protected words in TYPO3 Backend -> Search

Moreover by changing core/index configuration you need to reload the core to make the changes become active.
To reload configuration you can either restart the whole Solr server or simply reload a specific core.

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

    plugin.tx_solr.search.query.queryFields := addToList(test_textS\^1.0)


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

    plugin.tx_solr.search.query.queryFields = title\^20.0, title\^15.0


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


**I want to build the Dockerfile_full image on my mac with a local volume, how can i do that?**

|

The following example shows how to build the Dockerfile image and start a container with a mapped local volume (only for the data).
This was tested with "Docker for Mac" (not Docker Toolbox). Before executing the example, make sure, that you have added "~/solrdata" as allowed volume in the docker configuration.

::

    # build the image
    docker build -t typo3-solr -f Dockerfile .

    # create volume directory locally
    mkdir -p ~/solrdata

    # add solr group to volume directory
    sudo chown :8983 ~/solrdata

    # run docker container from image with volume
    docker run -d -p 127.0.0.1:8282:8983 -v ~/solrdata:/opt/solr/server/solr/data typo3-solr


**Can i index a https (SSL) site?**

Yes. You need a ssl certificate (can be self signed) and change the following setting:

::

    plugin.tx_solr.index.queue.pages.frontendDataHelper.scheme = https

|

**I want to index a value into a multiValue field from a user function. How can i do that?**

You can do that, by using SOLR_MULTIVALUE

::

    plugin.tx_solr.index.queue.indexConfigName {
        fields {
          somevalue_stringM = SOLR_MULTIVALUE
          somevalue_stringM {
               stdWrap.cObject = USER
               stdWrap.cObject.userFunc = Vendor\Ext\Classname->getValues
               separator=,
          }
        }
    }

|

**How can i use a configuration from AdditionalConfiguration.php when i deploy my application on several instances?**

The configuration of the connection is done with typoscript. When you want to use a configuration from TYPO3_CONF_VARS or from the system environment,
you can apply an stdWrap on the configuration that reads from these configurations.

The following example shows how a host can be configured in the AdditionalConfiguration.php and used in your typoscript to connect to solr:

The following line is added to AdditionalConfiguration.php

::

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['host'] = 'mysolrserver.de';

|

To use this configuration for the host, you can use a TEXT element in the configuration and use override.data to use the
value from the AdditionalConfiguration.php

::

    plugin.tx_solr.solr {
       host = TEXT
       host {
         value = localhost
         override.data = global:TYPO3_CONF_VARS|EXTCONF|solr|host
       }
    }

|

**How can I replace jQuery and/or jQuery UI versions or use different JavaScript library for searching field used by EXT:solr?**

You need to add following lines in your TypoScript setup:

::

    plugin.tx_solr.solr {
        javascriptFiles {
            library = EXT:your_site_extension/Resources/JavaScript/JQuery/jquery.XYZ.min.js
            ui = EXT:your_site_extension/Resources/JavaScript/JQuery/jquery-ui.XYZ.min.js
        }
    }

|

For more information please see :doc:`tx_solr.javascriptFiles <../Configuration/Reference/TxSolrJavaScriptFiles>`.

**I want to index extension records, what do i need to do?**

EXT:solr provides a flexible indexing for TYPO3 pages and records. You can add a custom indexing configuration for your own records with a valid TCA configuration.

You can read more about this in the section :doc:`IndexQueue Configuration <../Backend/IndexQueue>`.

The following things are important:

* The extension ships several examples in the Folder "Configuration/TypoScript/Examples", read them and try to undestand them.
* EXT:solr can not know the business logic of an extension to generate a link to a detail view. You need to use typolink to build an url that points to a valid, existing detail page.
* When you index records, e.g. news it these records are indexed in solr and point to a news details page. That's the reason why it makes sence to exclude the news detail page from the normal page indexing. Otherwise the indexing of this page will produce an error message, because only a url with a valid news uid produces a valid output.

|

**Are in EXT:solr some cli commands available?**

Yes, currently(v. 6.1) only one for initializing solr connections.
But check for new ones with :code:`bin/typo3 list` command.

|
