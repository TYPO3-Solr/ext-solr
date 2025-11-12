.. _routing-configure:

=================
Configure Routing
=================

Currently only one route enhancer exists. It's purpose is to mask facets inside the query string or as part of the :ref:`path segment <routing-facet-in-path>`.

The basement for routing is the enhancer `SolrFacetMaskAndCombineEnhancer`. Open your site configuration with an editor of your choice.

Locate the section of route enhancer `routeEnhancers`. If section available, add `routeEnhancers` to your configuration.

Use enhancer `SolrFacetMaskAndCombineEnhancer` as type of your route, and limit it to pages where the route should apply.

The extension key has to be set to `tx_solr`.

Solr specific configuration will be placed inside the key `solr`.

..  note::
    To use the EXT:solr possibility to create speaking URLs for Solr facets, activate the option enableRouteEnhancer in the
    Extension Configuration.

The following example shows the basic enhancer configuration.

.. code-block:: yaml

    routeEnhancers:
      products:
        type: SolrFacetMaskAndCombineEnhancer
        limitToPages:
          - 42
        extensionKey: tx_solr
        solr:

If you use something else than *tx_solr* for the plugin namespace (see :ref:`conf-tx-solr-view`) you need additional configuration.

Change the *extensionKey* from *tx_solr* to your plugin namespace.

.. code-block:: yaml

    routeEnhancers:
      products:
        type: SolrFacetMaskAndCombineEnhancer
        limitToPages:
          - 42
        extensionKey: search

The next step is to configure a path segment or to mask the facets inside the query.
