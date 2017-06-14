===========
Result List
===========

The most important part of a search are the results. The rendering of the results is done in the "Results.html" template
(Located in Templates/Frontend/Search/Results.html)

The following part of the default template iterates over the results and renders every document with the Document.html partial (Partials/Frontend/Result/Document.html)

.. code-block:: xml

	<s:widget.resultPaginate resultSet="{resultSet}">
		<ol start="{pagination.displayRangeStart}" class="results-list">
			<f:for each="{documents}" as="document">
				<f:render partial="Result/Document" section="Document"
				 arguments="{resultSet:resultSet, document:document}" />
			</f:for>
		</ol>
	</s:widget.resultPaginate>

This structure allows you to use e.g. the fluid if ViewHelper to render a result with a different partial, based on a field value.
But as you see in the template above, by default the partial "Result/Document" is used.

The "document" partial is getting the document object. In our case this is an instance of "ApacheSolrForTypo3\Solrfluid\Domain\Search\ResultSet\SearchResult"
the api of this object allows to get the solr field content with "Document->getFieldName()" that can be used as "document.fieldName" in fluid.




