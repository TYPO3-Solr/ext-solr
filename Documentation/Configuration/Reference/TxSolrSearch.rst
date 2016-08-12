.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. raw:: latex

    \newpage

.. raw:: pdf

   PageBreak

.. _conf-tx-solr-search:

tx_solr.search
===============

The search section, you probably already guessed it, provides configuration options for the all things related to actually searching the index, setting query parameters, formatting and processing result documents and the result listing.

targetPage
----------

:Type: Integer
:TS Path: plugin.tx_solr.search.targetPage
:Default: 0
:Since: 1.0

Sets the target page ID for links. If it is empty or 0, the current page ID will be used.

Note: This setting can be overwritten by the plugins flexform.

initializeWithEmptyQuery
------------------------

:Type: Boolean
:TS Path: plugin.tx_solr.search.initializeWithEmptyQuery
:Default: 0
:Options: 0,1
:Since: 1.0

If enabled, the results plugin (pi_results) issues a "get everything" query during initialization. This is useful, if you want to create a page that shows all available facets although no search has been issued by the user yet. Note: Enabling this option alone will not show results of the get everything query. To also show the results of the query, see option showResultsOfInitialEmptyQuery below.

showResultsOfInitialEmptyQuery
------------------------------

:Type: Boolean
:TS Path: plugin.tx_solr.search.showResultsOfInitialEmptyQuery
:Default: 0
:Options: 0,1
:Since: 1.0

Requires initializeWithEmptyQuery (above) to be enabled to have any effect. If enabled together with initializeWithEmptyQuery the results of the initial "get everything" query are shown. This way, in combination with a filter you can easily list a predefined set of results.

keepExistingParametersForNewSearches
------------------------------------

:Type: Boolean
:TS Path: plugin.tx_solr.search.keepExistingParametersForNewSearches
:Default: 0
:Options: 0,1
:Since: 2.0

When doing a new search, existing parameters like filters will be carried over to the new search. This is useful for a scenario where you want to list all available documents first, then allow the user to filter the documents using facets and finally allow him to specify a search term to refine the search.

query
-----

The query sub-section defines a few query parameters for the query that will be sent to the Solr server later on. Some query parameters are also generated and set by the extension itself, f.e. when using facets.

query.allowEmptyQuery
~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.query.allowEmptyQuery
:Default: 0
:Options: 0,1
:Since: 1.4

If enabled, empty queries are allowed.

query.allowedSites
~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.query.allowedSites
:Since: 2.2
:Default: __solr_current_site

When indexing documents (pages, records, files, ...) into the Solr index, the solr extension adds a "siteHash". The siteHash is used to allow indexing multiple sites into one index and still have each site only find its own documents. This is achieved by adding a filter on the siteHash.

Sometimes though, you want to search across multiple domains, then the siteHash is a blocker. Using the allowedSites setting you can set a comma-separated list of domains who's documents are allowed to be included in the current domain's search results. The default value is **__solr_current_site** which is a magic string/variable that is replaced with the current site's domain when querying the Solr server.

:Since: 3.0

Version 3.0 introduced a couple more magic keywords that get replaced:

- **__current_site** same as **__solr_current_site**
- **__all** Adds all domains as allowed sites
- \* (asterisk character) Same as **__all**

query.getParameter
~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.query.getParameter
:Since: 2.2
:Default: tx_solr|q

The GET query parameter name used in URLs. Useful for cases f.e. when a website tracking tool does not support the default array GET parameters.

The option expects a string, you can also define an array in the form of arrayName|arrayKey.

Example:

|

.. code-block:: typoscript

    plugin.tx_solr.search.query.getParameter = q


query.queryFields (query.fields)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.query.queryFields
:Since: 1.0
:Default: content^40.0, title^5.0, keywords^2.0, tagsH1^5.0, tagsH2H3^3.0, tagsH4H5H6^2.0, tagsInline^1.0
:Note: query.fields has been renamed to query.queryFields in version 3.0

Defines what fields to search in the index. Fields are defined as a comma separated list. Each field can be given a boost by appending the boost value separated by the ^ character, that's Lucene query language. The boost value itself is a float value, pay attention to using a dot as the separator for the fractions. Use this option to add more fields to search.

The boost take influence on what score a document gets when searching and thus how documents are ranked and listed in the search results. A higher score will move documents up in the result listing. The boost is a multiplier for the original score value of a document for a search term.

By default if a search term is found in the content field the documents gets scored / ranked higher as if a term was found in the title or keywords field. Although the default should provide a good setting, you can play around with the boost values to find the best ranking for your content.

query.returnFields
~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.query.returnFields
:Since: 3.0
:Default: \*, score

Limits the fields returned in the result documents, by default returns all field plus the virtual score field.

query.minimumMatch
~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.query.minimumMatch
:Since: 1.2, 2.0
:Default: (empty)
:See: `Apache Solr Wiki mm / Minimum Should Match <http://wiki.apache.org/solr/DisMaxRequestHandler#mm_.28Minimum_.27Should.27_Match.29>`_

Sets the minimum match mm query parameter.
By default the mm query parameter is set in solrconfig.xml as 2<-35%. This means that for queries with less than three words they all must match the searched fields of a document. For queries with three or more words at least 65% of them must match rounded up.

Please consult the link to the Solr wiki for a more detailed description of the mm syntax.

.. _conf-tx-solr-search-boostFunction:

query.boostFunction
~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.query.boostFunction
:Since: 1.2, 2.0
:Default: (empty)
:See: `Apache Solr Wiki / TheDisMaxQueryParser BoostFunction <https://cwiki.apache.org/confluence/display/solr/The+DisMax+Query+Parser#TheDisMaxQueryParser-Thebf%28BoostFunctions%29Parameter>`_
:See: `Apache Solr Wiki / Function Queries <https://cwiki.apache.org/confluence/display/solr/Function+Queries>`_
:Example: recip(ms(NOW,created),3.16e-11,1,1)

A boost function can be useful to influence the relevance calculation and boost some documents to appear more at the beginning of the result list.
Technically the parameter will be mapped to the **"bf"** parameter in the solr query.

Use cases for example could be:

* "Give never documents a higher priority":

    |

    This could be done with a recip function:

    |

    *recip(ms(NOW,created),3.16e-11,1,1)*

    |

* "Give documents with a certain field value a higher priority":

    |


    This could be done with:

    |

    *termfreq(type,'tx_solr_file')*

    |

|

.. _conf-tx-solr-search-boostQuery:

query.boostQuery
~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.query.boostQuery
:Since: 2.0
:Default: (empty)
:See: `Apache Solr Wiki / TheDisMaxQueryParser BoostQuery <https://cwiki.apache.org/confluence/display/solr/The+DisMax+Query+Parser#TheDisMaxQueryParser-Thebq%28BoostQuery%29Parameter>`_

Sets the boost function **bq** query parameter.

Allows to further manipulate the score of a document by using Lucene syntax queries. A common use case for boost queries is to rank documents of a specific type higher than others.

Please consult the link to the Solr wiki for a more detailed description of boost functions.

Example (boosts tt_news documents by factor 10):

|

.. code-block:: typoscript

    plugin.tx_solr.search.query.boostQuery = (type:tt_news)^10


query.filter
~~~~~~~~~~~~

:Type: Array
:TS Path: plugin.tx_solr.search.query.filter
:Since: 1.0
:See: `Lucene Documentation / Query Parser Syntax <http://lucene.apache.org/core/old_versioned_docs/versions/3_0_0/queryparsersyntax.html>`_

Allows to predefine filters to apply to a search query. You can add multiple filters through a name to Lucene filter mapping. The filters support stdWrap.

Example:

|

.. code-block:: typoscript

    plugin.tx_solr.search.query.filter {
        pagesOnly = type:pages
        johnsPages = author:John
        badKeywords = {foo}
        badKeywords.wrap = -keywords:|
        badKeywords.data = GP:q
    }


query.filter.__pageSections
~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: comma-separated list of page IDs
:TS Path: plugin.tx_solr.search.query.filter.__pageSections
:Since: 3.0

This is a magic/reserved filter (thus the double underscore). It limits the query and the results to certain branches/sections of the page tree. Multiple starting points can be provided as a comma-separated list of page IDs.


query.sortBy
~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.query.sortBy
:Since: 1.0

Allows to set a custom sorting for the query. By default Solr will sort by releavance, using this setting you can sort by any sortable field.

Needs a Solr field name followed by asc for ascending order or desc for descending.

Example:

|

.. code-block:: typoscript

    plugin.tx_solr.search.query.sortBy = title asc


results
-------

results.resultsHighlighting
~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.results.resultsHighlighting
:Since: 1.0
:Default: 0
:See: `Apache Solr Wiki / FastVectorHighlighter <https://cwiki.apache.org/confluence/display/solr/FastVector+Highlighter>`_

En-/disables search term highlighting on the results page.

Note:  The FastVectorHighlighter is used by default (Since 4.0) if fragmentSize is set to at least 18 (this is required by the FastVectorHighlighter to work).

results.resultsHighlighting.highlightFields
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.results.resultsHighlighting.highlightFields
:Since: 1.0
:Default: content

A comma-separated list of fields to highlight.

Note: The highlighting in solr (based on FastVectorHighlighter requires a field datatype with **termVectors=on**, **termPositions=on** and **termOffsets=on** which is the case for the content field).
If you add other fields here, make sure that you are using a datatype where this is configured.



results.resultsHighlighting.fragmentSize
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Integer
:TS Path: plugin.tx_solr.search.results.resultsHighlighting.fragmentSize
:Since: 1.0
:Default: 200

The size, in characters, of fragments to consider for highlighting. "0" indicates that the whole field value should be used (no fragmenting).

results.resultsHighlighting.fragmentSeparator
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.results.resultsHighlighting.fragmentSeparator
:Since: 3.0
:Default: [...]

When highlighting is activated Solr highlights the fields configured in highlightFields and can return multiple fragments of fragmentSize around the highlighted search word. These fragments are used as teasers in the results list. fragmentSeparator allows to configure the glue string between those fragments.

results.resultsHighlighting.wrap
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.results.resultsHighlighting.wrap
:Since: 1.0
:Default: <span class="results-highlight">|</span>

The wrap for search terms to highlight.

results.siteHighlighting
~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.results.siteHighlighting
:Since: 2.0
:Default: 0

Activates TYPO3's highlighting of search words on the actual pages. The words a user searched for will be wrapped with a span and CSS class csc-sword
Highlighting can be styled using the CSS class csc-sword, you need to add the style definition yourself for the complete site.

results.resultsPerPage
~~~~~~~~~~~~~~~~~~~~~~

:Type: Integer
:TS Path: plugin.tx_solr.search.results.resultsPerPage
:Since: 1.0
:Default: {$plugin.tx_solr.search.results.resultsPerPage}

Sets the number of shown results per page.

results.resultsPerPageSwitchOptions
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.results.resultsPerPageSwitchOptions
:Since: 1.0
:Default: 10, 25, 50

Defines the shown options of possible results per page.

results.fieldProcessingInstructions
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Array
:TS Path: plugin.tx_solr.search.results.fieldProcessingInstructions
:Since: 1.0
:Options: timestamp, utf8Decode, skip

Mapping of fieldname to processing instructions. Available instructions: timestamp, utf8Decode, skip (removes the field from the result).

The utf8Decode option has been removed in version 2.8.

results.fieldRenderingInstructions
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: cObject
:TS Path: plugin.tx_solr.search.results.fieldRenderingInstructions
:Since: 1.0

Additional rendering instructions for specified fields.

results.pagebrowser
~~~~~~~~~~~~~~~~~~~

:Since: 2.0
:TS Path: plugin.tx_solr.search.results.pagebrowser

Allows to set configuration options for the pagebrowser provided by EXT:pagebrowse.

results.ignorePageBrowser
~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.results.ignorePageBrowser
:Since: 1.0
:Default: 0
:Options: 0, 1

If enabled, the selected page will be ignored. Useful if you place two search plugins on a page but want one of the to always show the first results only.

results.showDocumentScoreAnalysis
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.results.showDocumentScoreAnalysis
:Since: 2.5-dkd
:Default: 0
:Options: 0,1

If enabled, the analysis and display of the score analysis for logged in backend users will be initialized.

results.markResultTypeBoundaries
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.results.markResultTypeBoundaries
:Since: 2.0, 2.5-dkd
:Default: 0
:Options: 0,1

This option allows for some kind of fake or client side (EXT:solr being the client) grouping of results. First you need to sort results by the type field. Then whenever the type field changes during rendering of the results, special fields are added to the documents at the result document type boundary. In your template you can then use an IF condition to insert markup at those boundaries based on those fields' values. The special document field names are "typeBegin" and "typeEnd". They have a value set to the new/old document type appended with "_begin"/"end".

Example:

A result set with documents of types tt_news, pages, and tt_address:

|

.. code-block:: text

    1 pages      -> typeBegin = pages_begin
    2 pages
    3 pages      -> typeEnd   = pages_end
    4 tt_address -> typeBegin = tt_address_begin
    5 tt_address
    6 tt_address -> typeEnd   = tt_address_end
    7 tt_news    -> typeBegin = tt_news_begin
    8 tt_news
    9 tt_news    -> typeEnd   = tt_news_end

Example template snippet:

|

.. code-block:: html

    <!-- ###LOOP:RESULT_DOCUMENTS### begin -->
    <!-- ###LOOP_CONTENT### -->

    <!-- ###IF:###RESULT_DOCUMENT.typeBegin###|==|pages_begin### begin -->
    <li>Pages</li>
    <!-- ###IF:###RESULT_DOCUMENT.typeBegin###|==|pages_begin### end -->

    <!-- ... regular page results here ... -->

    <!-- ###IF:###RESULT_DOCUMENT.typeEnd###|==|pages_end### begin -->
    <li>Pages End</li>
    <!-- ###IF:###RESULT_DOCUMENT.typeEnd###|==|pages_end### end -->

    ...

    <!-- ###IF:###RESULT_DOCUMENT.typeBegin###|==|tt_address_begin### begin -->
    <li>Contacts</li>
    <!-- ###IF:###RESULT_DOCUMENT.typeBegin###|==|tt_address_begin### end -->

    <!-- ###IF:###RESULT_DOCUMENT.typeBegin###|==|tt_news_begin### begin -->
    <li>News</li>
    <!-- ###IF:###RESULT_DOCUMENT.typeBegin###|==|tt_news_begin### end -->

    <!-- ... more markup here ... -->

    <!-- ###LOOP_CONTENT### -->
    <!-- ###LOOP:RESULT_DOCUMENTS### end -->


spellchecking
-------------

spellchecking
~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.spellchecking
:Since: 1.0
:Default: 0

Set `plugin.tx_solr.search.spellchecking = 1` to enable spellchecking / did you mean.

spellchecking.wrap
~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.spellchecking.wrap
:Since: 1.0
:Default: \|<div class="spelling-suggestions">###LLL:didYouMean### \|</div>\|

This can be used to configure a custom wrap for your did you mean rendering.

lastSearches
------------

lastSearches
~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.lastSearches
:Since: 1.3-dkd
:Default: 0

Set `plugin.tx_solr.lastSearches = 1` to display a list of the last searches.

lastSearches.limit
~~~~~~~~~~~~~~~~~~

:Type: Integer
:TS Path: plugin.tx_solr.search.lastSearches.limit
:Since: 1.3-dkd
:Default: 10

Defines the number of last searches, that should get minded.

lastSearches.mode
~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.lastSearches.mode
:Since: 1.3-dkd
:Default: user
:Options: user, global

If mode is user, keywords will get stored into the session. If mode is global keywords will get stored into the database.

frequentSearches
----------------

frequentSearches
~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.frequentSearches
:Since: 1.3-dkd, 2.8
:Default: 0

Set  `plugin.tx_solr.search.frequentSearches = 1` to display a list of the frequent / common searches.

frequentSearches.useLowercaseKeywords
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.frequentSearches.useLowercaseKeywords
:Since: 2.9
:Default: 0

When enabled, keywords are written to the statistics table in lower case.

frequentSearches.minSize
~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Integer
:TS Path: plugin.tx_solr.search.frequentSearches.minSize
:Since: 1.3-dkd, 2.8
:Default: 14

The difference between frequentSearches.maxSize and frequentSearches.minSize is used for calculating the current step.

frequentSearches.maxSize
~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Integer
:TS Path: plugin.tx_solr.search.frequentSearches.maxSize
:Since: 1.3-dkd, 2.8
:Default: 32

The difference between frequentSearches.maxSize and frequentSearches.minSize is used for calculating the current step.

frequentSearches.limit
~~~~~~~~~~~~~~~~~~~~~~

:Type: Integer
:TS Path: plugin.tx_solr.search.frequentSearches.limit
:Since: 1.3-dkd, 2.8
:Default: 20

Defines the maximum size of the list by frequentSearches.select.

frequentSearches.select
~~~~~~~~~~~~~~~~~~~~~~~

:Type: cObject
:TS Path: plugin.tx_solr.search.frequentSearches.select
:Since: 1.3-dkd, 2.8

Defines a database connection for retrieving statistics.

sorting
-------

sorting
~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.sorting
:Since: 1.0
:Default: 0

Set `plugin.tx_solr.search.sorting = 1`  to allow sorting of results.

sorting.defaultOrder
~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.sorting.defaultOrder
:Since: 1.0
:Default: ASC
:Options: ASC, DESC

Sets the default sort order for all sort options.

sorting.options
~~~~~~~~~~~~~~~

This is a list of sorting options. Each option has a field and label to be used. By default the options title, type, author, and created are configured, plus the virtual relevancy field which is used for sorting by default.

Example:

|

.. code-block:: typoscript

    plugin.tx_solr.search {
        sorting {
            options {
                relevance {
                    field = relevance
                    label = Relevance
                }

                title {
                    field = sortTitle
                    label = Title
                }
            }
        }
    }

|

Note: As mentioned before **relevance** is a virtual field that is used to **reset** the sorting. Sorting by relevance means to have the order provided by the scoring from solr. That the reason why sorting **descending** on relevance is not possible.

sorting.options.[optionName].label
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String / stdWrap
:TS Path: plugin.tx_solr.search.sorting.options.[optionName].label
:Since: 1.0

Defines the name of the option's label. Supports stdWrap.

sorting.options.[optionName].field
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String / stdWrap
:TS Path: plugin.tx_solr.search.sorting.options.[optionName].field
:Since: 1.0

Defines the option's field. Supports stdWrap.

sorting.options.[optionName].defaultOrder
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.sorting.options.[optionName].defaultOrder
:Since: 2.2
:Default: ASC
:Options: ASC, DESC

Sets the default sort order for a particular sort option.

sorting.options.[optionName].fixedOrder
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.sorting.options.[optionName].fixedOrder
:Since: 2.2
:Default: ASC
:Options: ASC, DESC

Sets a fixed sort order for a particular sort option that can not be changed.


faceting
--------

faceting
~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting
:Since: 1.0
:Default: 0

Set `plugin.tx_solr.search.faceting = 1` to enable faceting.

faceting.minimumCount
~~~~~~~~~~~~~~~~~~~~~

:Type: Integer
:TS Path: plugin.tx_solr.search.faceting.minimumCount
:Since: 1.0
:Default: 1
:See: `Apache Solr Wiki / Faceting mincount Parameter <https://cwiki.apache.org/confluence/display/solr/Faceting#Faceting-Thefacet.mincountParameter>`_

This indicates the minimum counts for facet fields should be included in the response.

faceting.sortBy
~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.sortBy
:Since: 1.0
:Default: count
:Options: count, index, 1, 0, true, false, alpha (1.2, 2.0), lex (1.2, 2.0)
:See: `Apache Solr Wiki / Faceting sortParameter Parameter <https://cwiki.apache.org/confluence/display/solr/Faceting#Faceting-Thefacet.sortParameter>`_

Defines how facet options are sorted, by default they are sorted by count of results, highest on top. count, 1, true are aliases for each other.

Facet options can also be sorted alphabetically (lexicographic by indexed term) by setting the option to index. index, 0, false, alpha (from version 1.2 and 2.0), and lex (from version 1.2 and 2.0) are aliases for index.

faceting.limit
~~~~~~~~~~~~~~

:Type: Integer
:TS Path: plugin.tx_solr.search.faceting.limit
:Since: 1.0
:Default: 10

Number of options to display per facet. If more options are returned by Solr, they are hidden and can be expanded by clicking a "show more" link. This feature uses a small javascript function to collapse/expand the additional options.

faceting.singleFacetMode
~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting.singleFacetMode
:Since: 1.2, 2.0
:Default: 0
:Options: 0, 1

If enabled, the user can only select an option from one facet at a time.

Lets say you have two facets configured, type and author. If the user selects a facet option from type its filter is added to the query. Normally when selecting another option from the other facet - the author facet - this would lead to having two facet filters applied to the query. When this option is activated the option from the author facet will simply replace the first option from the type facet.

faceting.keepAllFacetsOnSelection
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting.keepAllFacetsOnSelection
:Since: 2.2
:Default: 0
:Options: 0, 1

When enabled selecting an option from a facet will not reduce the number of options available in other facets.

faceting.removeFacetLinkText
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.removeFacetLinkText
:Since: 1.0
:Default: @facetLabel: @facetText

Defines the text for a link used for removing a given facet from the search results. You can use the following placeholders in this text: @facetValue, @facetName, @facetLabel, @facetText

faceting.showAllLink.wrap
~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.showAllLink.wrap
:Since: 1.0
:Default: <li>|</li>

Defines the output of the "Show more" link, that is rendered if there are more facets given than set by faceting.limit.

faceting.showEmptyFacets
~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting.showEmptyFacets
:Since: 1.3
:Default: 0
:Options: 0, 1

By setting this option to 1, you will allow rendering of empty facets. Usually, if a facet does not offer any options to filter a resultset of documents, the facet header will not be shown. Using this option allows the header still to be rendered when no filter options are provided.

faceting.facetLinkATagParams
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.facetLinkATagParams
:Since: 2.0
:Default: rel="nofollow"

With this option you can add A-Tag attributes for links of all facet-options.

|

.. code-block:: typoscript

    plugin.tx_solr.search.faceting.facetLinkATagParams = class="green"


faceting.facetLinkUrlParameters
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.facetLinkUrlParameters
:Since: 2.8

Allows to add URL GET parameters to the links build in facets.

faceting.facetLinkUrlParameters.useForFacetResetLinkUrl
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting.facetLinkUrlParameters.useForFacetResetLinkUrl
:Since: 2.8

Allows to prevent adding the URL parameters to the facets reset link by setting the option to 0.

faceting.facets
~~~~~~~~~~~~~~~

:Type: Array
:TS Path: plugin.tx_solr.search.faceting.facets
:Since: 1.0
:Default: type
:See: `Apache Solr Wiki / Faceting Overview <http://wiki.apache.org/solr/SolrFacetingOverview>`_

Defines which fields you want to use for faceting. It's a list of facet configurations.

|

.. code-block:: typoscript

    plugin.tx_solr.search.faceting.facets {
      type {
        field = type
        label = Content Type
      }

      category {
        field = category_stringM
        label = Category
      }
    }


faceting.facets.[facetName] - single facet configuration
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

You can add new facets by simply adding a new facet configuration in TypoScript. [facetName] represents the facet's name and acts as a configuration "container" for a single facet. All configuration options for a facet are defined within that "container".

A facet will use the values of a configured index field to offer these values as filter options to your site's visitors. You need to make sure that the facet field's type allows to sort the field's value; like string, int, and other primitive types.

To configure a facet you only need to provide the label and field configuration options, all other configuration options are optional.

faceting.facets.[facetName].field
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].field
:Since: 1.0
:Required: yes

Which field to use to create the facet.

faceting.facets.[facetName].label
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].label
:Since: 1.0
:Required: yes

Used as a headline or title to describe the options of a facet.

faceting.facets.[facetName]. selectingSelectedFacetOptionRemovesFilter
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].selectingSelectedFacetOptionRemovesFilter
:Since: 1.2, 2.0
:Default: 0
:Options: 0, 1

Activating this option for a facet makes the facets option links behave like on/off switches: You click them once to activate a facet, you click them a second time to deactivate the facet again.

Feel free to suggest a better name for this option.

faceting.facets.[facetName].keepAllOptionsOnSelection
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].keepAllOptionsOnSelection
:Since: 1.2, 2.0
:Default: 0
:Options: 0, 1

Normally, when clicking any option link of a facet this would result in only that one option being displayed afterwards. By setting this option to one, you can prevent this. All options will still be displayed.

This is useful if you want to allow the user to select more than one option from a single facet.

faceting.facets.[facetName].singleOptionMode
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].singleOptionMode
:Since: 1.3, 2.0
:Default: 0
:Options: 0, 1

When enabled together with keepAllOptionsOnSelection a user can select one option of the facet only at a time. Selecting a different option than the currently selected option results in the new option to replace the old one. The behavior thus is similar to a select box or a set of radio buttons.

This option can not be used together with selectingSelectedFacetOptionRemovesFilter as it overrides its behavior.

faceting.facets.[facetName].operator
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].operator
:Since: 1.2, 2.0
:Default: AND
:Options: OR, AND

When configuring a facet to allow selection of multiple options, you can use this option to decide whether multiple selected options should be combined using AND or OR.

faceting.facets.[facetName].sortBy
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].sortBy
:Since: 1.2
:Default: -
:Options: alpha (aliases: index, lex)

Sets how a single facet's options are sorted, by default they are sorted by number of results, highest on top.
Facet options can also be sorted alphabetically by setting the option to alpha.

faceting.facets.[facetName].manualSortOrder
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].manualSortOrder
:Since: 2.2

By default facet options are sorted by the amount of results they will return when applied. This option allows to manually adjust the order of the facet's options. The sorting is defined as a comma-separated list of options to re-order. Options listed will be moved to the top in the order defined, all other options will remain in their original order.

Example - We have a category facet like this:

|

.. code-block:: bash

    News Category
    + Politics (256)
    + Sports (212)
    + Economy (185)
    + Culture (179)
    + Health (132)
    + Automobile (99)
    + Travel (51)

Using `faceting.facets.[facetName].manualSortOrder = Travel, Health` will result in the following order of options:

|

.. code-block:: bash

    News Category
    + Travel (51)
    + Health (132)
    + Politics (256)
    + Sports (212)
    + Economy (185)
    + Culture (179)
    + Automobile (99)

faceting.facets.[facetName].reverseOrder
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].reverseOrder
:Since: 3.0
:Default: 0
:Options: 0, 1

Reverses the order of facet options.

faceting.facets.[facetName].showEvenWhenEmpty
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].showEvenWhenEmpty
:Since: 2.0
:Default: 0
:Options: 0, 1

Allows you to display a facet even if it does not offer any options (is empty) and although you have set `plugin.tx_solr.search.faceting.showEmptyFacets = 0`.

faceting.facets.[facetName].includeInAvailableFacets
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].includeInAvailableFacets
:Since: 1.3
:Default: 1
:Options: 0, 1

By setting this option to 0, you can prevent rendering of a given facet within the list of available facets.

This is useful if you render the facet somewhere eles on the page using the facet view helper and don't want the facet to be rendered twice.

faceting.facets.[facetName].includeInUsedFacets
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].includeInUsedFacets
:Since: 2.0
:Default: 1
:Options: 0, 1

By setting this option to 0, you can prevent rendering of a given facet within the list of used facets.

faceting.facets.[facetName].type
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].type
:Since: 2.0

Defines the type of the facet. By default all facets will render their facet options as a list. PHP Classes can be regsitered to add new types. Using this setting will allow you to use such a type and then have the facet's options rendered and processed by the registered PHP class.

faceting.facets.[facetName].[type]
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Array
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].[type]
:Since: 2.0

When setting a special type for a facet you can set further options for this type using this array.

Example (numericRange facet displayed as a slider):

|

.. code-block:: typoscript

    plugin.tx_solr.search.faceting.facets.size {
         field = size_intS
         label = Size

         type = numericRange
         numericRange {
             start = 0
             end = 100
             gap = 1
        }
    }

faceting.facets.[facetName].requirements.[requirementName]
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Array
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].requirements.[requirementName]
:Since: 2.2

Allows to define requirements for a facet to be rendered. These requirements are dependencies on values of other facets being selected by the user. You can define multiple requirements for each facet. If multiple requirements are defined, all must be met before the facet is rendered.

Each requirement has a name so you can easily recognize what the requirement is about. The requirement is then defined by the name of another facet and a list of comma-separated values. At least one of the defined values must be selected by the user to meet the requirement.

There are two magic values for the requirement's values definition:

    * __any: will mark the requirement as met if the user selects any of the required facet's options
    * __none: marks the requirement as met if none of the required facet's options is selected. As soon as any of the required facet's options is selected the requirement will not be met and thus the facet will not be rendered


Example of a category facet showing only when the user selects the news type facet option:

|

.. code-block:: typoscript

    plugin.tx_solr {
        search {
            faceting {
                facets {
                    type {
                        label = Content Type
                        field = type
                    }

                    category {
                        label = Category
                        field = category_stringS
                        requirements {
                            typeIsNews {
                              # typeIsNews is the name of the requirement, c
                              # choose any so you can easily  recognize what it does
                              facet = type
                              # The name of the facet as defined above
                              values = news
                              # The value of the type facet option as
                              # it is stored in the Solr index
                            }
                        }
                    }
                }
            }
        }
    }

faceting.facets.[facetName].renderingInstruction
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: cObject
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].renderingInstruction
:Since: 1.0

Overwrites how single facet options are rendered using TypoScript cObjects.

Example: (taken from issue #5920)

|

.. code-block:: typoscript

    plugin.tx_solr {
        search {
            faceting {
                facets {
                    type {
                        renderingInstruction = CASE
                        renderingInstruction {
                            key.field = optionValue

                            pages = TEXT
                            pages.value = Pages
                            pages.lang.de = Seiten

                            tx_solr_file = TEXT
                            tx_solr_file.value = Files
                            tx_solr_file.lang.de = Dateien

                            tt_news = TEXT
                            tt_news.value = News
                            tt_news.lang.de = Nachrichten
                        }
                    }

                    language {
                        renderingInstruction = CASE
                        renderingInstruction {
                            key.field = optionValue

                            0 = TEXT
                            0.value = English
                            0.lang.de = Englisch

                            1 = TEXT
                            1.value = German
                            1.lang.de = Deutsch
                        }
                    }
                }
            }
        }
    }

faceting.facets.[facetName].facetLinkATagParams
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: String
:TS Path: plugin.tx_solr.search.faceting.facets.[facetName].facetLinkATagParams
:Since: 2.0

With this option you have the possibility to add A-Tag attributes for all option-links of a single facet. This option overwrites the global setting "faceting.facetLinkATagParams". See "faceting.facetLinkATagParams" for more information.

elevation
---------

elevation
~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.elevation
:Since: 3.0
:Default: 0

Set plugin.tx_solr.search.elevation = 1 to enable content elevation in search results.

elevation.forceElevation
~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.elevation.forceElevation
:Since: 3.0
:Default: 1

Forces content elevation to be active.

elevation.markElevatedResults
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:Type: Boolean
:TS Path: plugin.tx_solr.search.elevation.markElevatedResults
:Since: 3.0
:Default: 1

If enabled, elevated results are marked with CSS class "results-elevated".

variants
--------

By using variants you can shrink down multiple documents with the same value in one field into one document and make similar documents available in the variants property.
By default the field variantId is used as Solr collapsing criteria. This can be used e.g. as one approach of deduplication to group similar documents into on "root" SearchResult.

To use the different variants of the documents you can access "document.variants" to access the expanded documents.

This can be used for example for de-duplication to list variants of the same document below a certain document.

Note: Internally this is implemented with Solr field collapsing

:Type: Boolean
:TS Path: plugin.tx_solr.search.variants
:Since: 6.0
:Default: 0

Set plugin.tx_solr.search.variants = 1 to enable the variants in search results.


variants.expand
~~~~~~~~~~~~~~~

Used to expand the document variants to the document.variants property.

:Type: Boolean
:TS Path: plugin.tx_solr.search.variants.expand
:Since: 6.0
:Default: 1

variants.variantField
~~~~~~~~~~~~~~~~~~~~~

Used to expand the document variants to the document.variants property.

**Note:**: The field must be a numeric field or a string field! Not a text field!

:Type: String
:TS Path: plugin.tx_solr.search.variants.variantField
:Since: 6.0
:Default: variantId

variants.limit
~~~~~~~~~~~~~~

Limit of expanded documents.

:Type: Integer
:TS Path: plugin.tx_solr.search.variants.limit
:Since: 6.0
:Default: 10
