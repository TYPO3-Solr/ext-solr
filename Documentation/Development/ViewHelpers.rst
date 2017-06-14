===========
ViewHelpers
===========

Beside the controllers, the domain objects and the templates we ship a few useful view helpers. To avoid a strong coupling between the extension and fluid as template engine we tried to keep all ViewHelpers as "slim" as possible. Whenever it was possible we moved the logic into custom service classes and just use them in the ViewHelper.

Since everything belongs to the **"SearchResultSet"** and we wanted to avoid the need ob passing this object around from "template to template" and "partial to partial" we decided to provide an own "ControllerContext" that referenced the "SearchResultSet". With this approach, it is possible to access the **"SearchResultSet"** in every ViewHelper.

With the current release we ship the following concrete ViewHelpers:

|

+---------------------------------+----------------------------------------------------------------+
| **Path**                        | **Description**                                                |
+---------------------------------+----------------------------------------------------------------+
| s:debug.documentScoreAnalyzer   | Used to render the score analysis.                             |
+---------------------------------+----------------------------------------------------------------+
| s:debug.query                   | Shows the solr query debug information.                        |
+---------------------------------+----------------------------------------------------------------+
| s:document.highlightResult      | Performs the highlighting on a document.                       |
+---------------------------------+----------------------------------------------------------------+
| s:document.relevance            | Shows the relevance information for a document.                |
+---------------------------------+----------------------------------------------------------------+
| s:facet.area.group              | Filters the facets in the rendering scope to one group.        |
+---------------------------------+----------------------------------------------------------------+
| s:uri.facet.addFacetItem        | Add's a facet item to the current url.                         |
+---------------------------------+----------------------------------------------------------------+
| s:uri.facet.removeAllFacets     | Removes all facet items from the current url.                  |
+---------------------------------+----------------------------------------------------------------+
| s:uri.facet.removeFacet         | Removes all options from one facet.                            |
+---------------------------------+----------------------------------------------------------------+
| s:uri.facet.removeFacetItem     | Removes a single facet item from the url.                      |
+---------------------------------+----------------------------------------------------------------+
| s:uri.facet.setFacetItem        | Sets one single item for a facet (and removes other setted)    |
+---------------------------------+----------------------------------------------------------------+
| s:uri.paginate.resultPage       | Creates a link to a result page of the current search.         |
+---------------------------------+----------------------------------------------------------------+
| s:uri.search.currentSearch      | Creates a link to the current search (with facets, sorting...) |
+---------------------------------+----------------------------------------------------------------+
| s:uri.search.startNewSearch     | Creates a link for a new search by a term.                     |
+---------------------------------+----------------------------------------------------------------+
| s:uri.sorting.removeSorting     | Creates a link to the current search and removes the sorting.  |
+---------------------------------+----------------------------------------------------------------+
| s:uri.sorting.setSorting        | Creates a link to the current search and sets a new sorting.   |
+---------------------------------+----------------------------------------------------------------+
| s:pageBrowserRange              | Provides the range data for the pagination.                    |
+---------------------------------+----------------------------------------------------------------+
| s:searchForm                    | Renders the searchForm.                                        |
+---------------------------------+----------------------------------------------------------------+
| s:translate                     | Custom translate ViewHelper (uses translations from ext:solr)  |
+---------------------------------+----------------------------------------------------------------+

