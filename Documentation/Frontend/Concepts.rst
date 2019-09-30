========
Concepts
========

Since EXT:solr 7.0.0 the old templating of EXT:solr was droppend and rendering with fluid was added.

Along with this change some concepts have changed:

* Until EXT:solr 7.0.0 EXT:solr css and javascript was loaded by EXT:solr automatically. In most cases you want to use custom css or you have custom javascript and the integrator want to decide which css or javascript to use. Therefore EXT:solr does not load it by default anymore and the integrator can load it with typoscript. EXT:solr provides a lot of example typoscript templates that load the default css or load the javascript that is needed to use a specific feature. Maybe take the time to explore the typoscript templates that are shipped with the extension to see how they are implemented.

* The were some old typosript settings that manipulate the data before it was passed to the view. With fluid this can also be done with core viewhelpers or custom viewhelpers. Therefore the following settings have been removed:
    * plugin.tx_solr.search.results.fieldRenderingInstructions
    * plugin.tx_solr.search.results.fieldProcessingInstructions (The fieldProcessingInstructions still exist at index time since there it is still needed)

* Beside that there were some template related settings in the typoscript, that can be solved just with fluid:
    * plugin.tx_solr.search.faceting.facetLinkATagParams or plugin.tx_solr.search.faceting.[facetName].facetLinkATagParams When you need something like this, you can just change the partials or render a facet with a custom partial (partialName = MyPartial) and add the properties there.
    * plugin.tx_solr.search.faceting.removeFacetLinkText This can be done just be rendering the text in the partial, that you need.

* The setting faceting.facets.[facetName].selectingSelectedFacetOptionRemovesFilter has been removed, since it is possible to build this functionality just with Fluid ViewHelpers. The file "EXT:solr/Resources/Private/Templates/Partials/Facets/OptionsToggle.html" shows how f:if together with ```option.selected``` can be used to have this behaviour.

