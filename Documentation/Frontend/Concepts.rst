========
Concepts
========

Since EXT:solr 7.0.0 the old templating of EXT:solr was droppend and rendering with fluid was added.

Along with this change some concepts have changed:

* Until EXT:solr 7.0.0 EXT:solr css and javascript was loaded by EXT:solr automatically. In most cases you want to use custom css or you have custom javascript and the integrator want to decide which css or javascript to use. Therefore EXT:solr does not load it by default anymore and the integrator can load it with typoscript. EXT:solr provides a lot of example typoscript templates that load the default css or load the javascript that is needed to use a specific feature. Maybe take the time to explore the typoscript templates that are shipped with the extension to see how they are implemented.
* The old concept of fieldRenderingInstructions modified the content of a result document by loosing the original value. Since manipulating the value of the field is something very view related, this can be done with TYPO3 core ViewHelpers or with a custom ViewHelper.
* The setting faceting.facets.[facetName].selectingSelectedFacetOptionRemovesFilter has been removed, since it is possible to build this functionality just with Fluid ViewHelpers. The file "EXT:solr/Resources/Private/Templates/Partials/Facets/OptionsToggle.html" shows
how f:if together with option.selected can be used to have this behaviour.
