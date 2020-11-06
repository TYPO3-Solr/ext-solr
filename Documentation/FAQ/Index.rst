.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt

.. _faq-index:

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
Please send an email to the `TYPO3 security team <mailto:security@typo3.org>`_ with detailed description of found vulnerability. For more details about security issue handling see `https://typo3.org/teams/security/contact-us/`

|

**Is there some chat/irc channel for EXT:solr available?**

Join us on the official `Slack for TYPO3 <https://forger.typo3.org/slack>`_ and get answers related to EXT:solr in the #ext-solr channel immediately!

|

**Which plugins(TYPO3 Frontend) are avalable?**

- Search: Form only
- Search: Form, Result, Additional Components
- Search: Frequent Searches

Just insert one of this plugins on corresponding page to fade in the search form and/or supply the front end with a search results.

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

**How can i use Fluid templates with EXT:solr < v7.0.0?**

For the Fluid rendering in EXT:Solr >= 5.0 <= 6.1 we provide the addon EXT:solrfluid, that allows you to render your search results with Fluid.
Since EXT:Solr 7.0 Fluid is the default templating engine.

|

**Which versions of EXT:solr / EXT:solrfal and EXT:solrfluid work together?**

Please check the :ref:`appendix-version-matrix`, the you can find the proposed version combinations.

|

**Pages are not indexed. I did everything by the book.**

You forgot to set `config.index_enable = 1` in your TypoScript setup: :ref:`started-enable-indexing`

|

**My indexed documents are empty, i can not find the content of a page?**

Did you configure the search markers ( "<!-- TYPO3SEARCH_begin -->" and "<!-- TYPO3SEARCH_end -->") on your page? Check the paragraph :ref:`started-search-markers` and make sure your page renders them.

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

    plugin.tx_solr.search.query.queryFields = title^20.0, title^15.0


*Use boostFunctions or boostQueries*

For use cases like "*news* are always more important then *pages*" or "Newer documents should be at the beginning" you can use boostFunctions (:ref:`conf-tx-solr-search-boostFunction`) or boostQueries (:ref:`conf-tx-solr-search-boostQuery`)

*The search term only exists as a synonym*

You can use the backend module synonyms (:ref:`backend-module-synonyms`) to maintain synonyms and configure solr to retrieve documents by a term that is not naturally inside the document.

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
    docker build -t typo3-solr -f Docker/SolrServer/Dockerfile .

    # create volume directory locally
    mkdir -p ~/solrdata

    # add solr group to volume directory
    sudo chown :8983 ~/solrdata

    # run docker container from image with volume
    docker run -d -p 127.0.0.1:8282:8983 -v ~/solrdata:/var/solr/data/data typo3-solr


**Can i index a https (SSL) site?**

Yes. You need a ssl certificate (can be self signed) and change the following setting:

::

    plugin.tx_solr.index.queue.pages.indexer.frontendDataHelper.scheme = https

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


**I want to overwrite the type field, why is this not possible?**

The type field is a system field that EXT:solr uses to keep the system in sync. Overwritting this field might result in inconsistency.
However, if you need something like a custom type you can also write the information to a dynamic solr field and use that one as a type.

The following example shows, how to fill the field "mytype_stringS" and build a facet on this field:

::

    plugin.tx_solr {
        index{
            queue{
                news = 1
                news {
                    table = tt_news

                    fields {
                        mytype_stringS = TEXT
                        mytype_stringS.value = news

                    }
                }
            }
        }
        search.faceting.facets.mytype_stringS {
             label = Type
             field = mytype_stringS
        }
    }

|


**I want to implement a toggle functionality for facet options as previously possible with selectingSelectedFacetOptionRemovesFilter. How can i do that?**

This is completely possible with Fluid core ViewHelpers and the domain model. The following steps are required.

Register a custom partial to render the facet:

::

    plugin.tx_solr.search.faceting.facets.<facetName>.partialName = OptionsToggle

This is the content of the OptionsToggle Partial (Feel free to adapt it to your needs):

::

    <h5 class="facet-label">{facet.label}</h5>
    <ul class="facet-option-list facet-type-options fluidfacet" data-facet-name="{facet.name}" data-facet-label="{facet.label}">
        <f:for each="{facet.options}" as="option" iteration="iteration">
            <li class="facet-option{f:if(condition:'{iteration.index} > 9', then:' tx-solr-facet-hidden')}" data-facet-item-value="{option.value}">
                <f:if condition="{option.selected}">
                    <f:then><a class="facet solr-ajaxified" href="{s:uri.facet.removeFacetItem(facet: facet, facetItem: option)}">{option.label}</a></f:then>
                    <f:else><a class="facet solr-ajaxified" href="{s:uri.facet.addFacetItem(facet: facet, facetItem: option)}">{option.label}</a></f:else>
                </f:if>
                <span class="facet-result-count">({option.documentCount})</span>
            </li>
        </f:for>
        <f:if condition="{facet.options -> f:count()} > 10">
            <li>
                <a href="#" class="tx-solr-facet-show-all" data-label-more="{s:translate(key:'faceting_showMore', extensionName:'solr')}"
                    data-label-less="{s:translate(key:'faceting_showFewer', extensionName:'solr')}">
                    <s:translate key="faceting_showMore" extensionName="solr">Show more</s:translate>
                </a>
            </li>
        </f:if>
    </ul>

**I want to store HTML in solr, how can i retrieve that?**

In general it is not recommend to allow html in the solr field. Especially when you index content that can be changed by the user.

However, if you want to allow html in a solr field, you need to add the field as trusted field and the content will not be escaped during the retrieval from solr.

The following example shows how to avoid html in the content field:

::

    plugin.tx_solr.search.trustedFields = url, content


Note: When you allow html in the content please make sure that the usage of crop ViewHelpers or a limit of the field length does not break your markup.

**I want to use two instances of the search plugin on the same page, how can i do that?**

If you want to use two search plugins on the same page you can add two instances and assign a different "Plugin Namespace" in the flexform. If you want to avoid, that both plugins react on the global "q" parameter, you can disable this also in the flexform. Each instance is using the querystring from <pluginNamespace>[q] then.


**How can i configure switchable templates for the results plugin?**

The following example shows, how you can configure a custom switchable entry template for the Results plugin:

::

   plugin.tx_solr {
       view {
           templateRootPaths.100 = EXT:your_config_extension/Resources/Private/Templates/
           partialRootPaths.100 = EXT:your_config_extension/Resources/Private/Partials/
           layoutRootPaths.100 = EXT:your_config_extension/Resources/Private/Layouts/
           templateFiles {
               results = Results
               results.availableTemplates {
                   default {
                       label = Default Searchresults Template
                       file = Results
                   }
                   products {
                       label = Products Template
                       file = ProductResults
                   }
               }
           }
       }
   }


**I want to use EXT:solr with a deployment and pass connection settings from outside e.g. by the environment, how can i do that?**

When you deploy a system automatically and you use EXT:solr there are some things that might be complicated:

* You want to use a different solr endpoint for each environment
* EXT:solr depends on an existing domain record

To avoid that, you can set or generate these settings in the TYPO3 AdditionalConfigruation.php file and use them in your system.

To configure a used domain you cat set:

::

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['sites'][###rootPageId###]['domains'] = ['mydomain.com'];

You can also define the data for your solr endpoints there and use them in the typoscript:

::

    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['sites'][###rootPageId###]['solrhost'] = 'solr1.local';
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['sites'][###rootPageId###]['solrport'] = 8083;

And use them in your TypoScript configuration:

::

    plugin.tx_solr {
        solr {
            host = TEXT
            host {
                value = {$plugin.tx_solr.solr.host}
                override.data = global:TYPO3_CONF_VARS|EXTCONF|solr|sites|###rootPageId###|solrhost
            }
            port = TEXT
            port {
                value = {$plugin.tx_solr.solr.port}
                override.data = global:TYPO3_CONF_VARS|EXTCONF|solr|sites|###rootPageId###|solrport
            }
        }
    }


**I want to use faceting.facets.[facetName].singleOptionMode why was it removed?**

This setting belongs to the rendering and not to the facet itself. You can implement the same behaviour just with the given ViewHelpers.

The behaviour is the same, when you just call the ViewHelper s:uri.facet.setFacetItem instead of s:uri.facet.addFacetItem, which semantically just overwrites the current value.

We've added an example partial "OptionsSinglemode" that shows this behaviour. The example TypoScript template "Search - (Example) Options with singlemode (only one option at a time)" shows how to use this partial in combination with the setting "keepAllOptionsOnSelection".


**I want to build a tab facet where all options remain, even with an option count of 0. How can i do that?**

This can be done with the combination of several settings:

::

    plugin.tx_solr.search.faceting {
        minimumCount = 0
        keepAllFacetsOnSelection = 1
        facets {
            typeTab {
                field = type
                keepAllOptionsOnSelection = 1
            }
        }
    }

The example above changes the minimumCount to 0, the default value i 1. Setting it to zero allows to have options without any results.
The setting "keepAllFacetsOnSelection" let all facets remain and with keepAllOptionsOnSelection the options in the type facet remain.

**How can i add a searchbox on every page?**

In most projects you want to add a searchbox on every content page. To support this, the default EXT:solr typoscript template provides the typoscript template path "plugin.tx_solr_PiSearch_Search" that contains a configured typoscript code to render the searchbox. When you want to add that to your project in the most cases you would need to refer to a search result page.
The following example shows how you can build a typoscript lib object that configures the target page for this plugin instance:

::

    lib.searchbox < plugin.tx_solr_PiSearch_Search
    lib.searchbox.search.targetPage = 4711

Afterwards you could render the typoscript path "lib.searchbox" with several ways in TYPO3, e.g. with a FLUID ViewHelper:

::

    <f:cObject typoscriptObjectPath="lib.searchbox" />

By adding the snippet to a generic tempate you could render the searchbox on every page.

**How can I index protected pages (htaccess protection)?**

Protected pages can be accessed by passing the htpasswd username and password to the indexing queue.
You can set the credentials by the following configuration:

::

	plugin.tx_solr.index.queue.pages.indexer.authorization.username = your_username
	plugin.tx_solr.index.queue.pages.indexer.authorization.password = your_password


As credentials are stored as plain text, go for sure that your web server does not serve your TypoScript files publicly \(protect the directory or by file endings\).
If you don't want to store plain text passwords, you can configure your web server to allow access from a specific domain (see below).

If you have multiple domains to index, the webserver requires the credentials for each domain accessed by the solr indexer. The extension passes the credentials only once, so you will run into errors on a multi domain environment.
Solution: Instead of passing the credentials as shown above, configure your webserver directory protection to allow access from the solr IP:

::

	AuthType Basic
	AuthUserFile /path/to/.htpasswd
	<RequireAny>
	        Require ip XXX.XX.XX.XX (the IP of the solr server)
	        Require valid-user
	</RequireAny>

Be aware, that this will allow all accesses by given IP.

**How can I use different host / port configurations in Solr v10 (e.g. for local environments)?**

While you could use TypoScript conditions to change the configuration for different project evironments in the past, you can now use environment variables in the config.yaml like shown below.

In your sites config.yaml:
::

	solr_host_read: '%env(SOLR_HOST)%'
	solr_port_read: '%env(SOLR_PORT)%'

In your .env file:

::

	SOLR_HOST=127.0.0.1
	SOLR_PORT=8983

Refer to TYPO3 documentation:
https://docs.typo3.org/m/typo3/reference-coreapi/master/en-us/ApiOverview/SiteHandling/UsingEnvVars.html#using-environment-variables-in-site-configuration
