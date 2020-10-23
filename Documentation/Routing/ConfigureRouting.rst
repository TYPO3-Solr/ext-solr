.. _routing-configure:

=================
Configure Routing
=================

Currently only one route enhancer exists. It's purpose is to mask facets inside the query string or as part of the :ref:`path segment <routing-facet-in-path>`.

The basement for routing is the enhancer `CombinedFacetEnhancer`. Open your site configuration with an editor of your choice.

Locate the section of route enhancer `routeEnhancers`. If section available, add `routeEnhancers` to your configuration.

Use enhancer `CombinedFacetEnhancer` as type of your route, and limit it to pages where the route should apply.

The extension key have to be set to `tx_solr`.

Solr specific configuration will placed inside of key `solr`.

The following example shows a the basement of the enhancer configuration.

.. code-block:: yaml

    routeEnhancers:
      products:
        type: CombinedFacetEnhancer
        limitToPages:
          - 42
        extensionKey: tx_solr
        solr:

If you use something else as *tx_solr* for the plugin namespace (see :ref:`conf-tx-solr-view`) you need additional configuration.

Add *pluginNamespace* below the key *solr* and configure your plugin namespace.

.. code-block:: yaml

    routeEnhancers:
      products:
        type: CombinedFacetEnhancer
        limitToPages:
          - 42
        extensionKey: tx_solr
        solr:
          pluginNamespace: 'search'

The next step is to configure a path segment or to mask the facets inside of the query.
