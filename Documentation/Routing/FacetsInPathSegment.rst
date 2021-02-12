.. _routing-facet-in-path:

=======================
Facets in path segments
=======================

This chapter describe how you can use a facet as part of your url path.

Imagine you have an online shop and use Solr to display the products. It would be convenient for the customer if can call an url path like `/products/candy`.

The first step is to create a site `/products` and place the Solr plugin as content.

Configure the path segment
--------------------------

In our example we are using facet `productType` to filter the search result for a specific product type.

The first step is to define a route path. Because we have already a side called `/products` only need to define a path contain the facet.

The name itself needs no connection to the facet, so you can choose something different.

.. code-block:: yaml

  routeEnhancers:
    products:
      routePath: '/{productType}'


The next step is to map the variable to the facet itself.

Map the variable to the facet
-----------------------------

The mapping from variable to facet differs from the standard as you know from TYPO3 or symfony.

A facet inside of the query is a composition of facet and value.

Example:

	/products/?tx_solr[filter][0]=taste:matcha&tx_solr[filter][1]=productType:candy&tx_solr[filter][2]=color:yellow&tx_solr[filter][3]=color:green&tx_solr[filter][4]=taste:sweet

This example shows another issues:

* there is not specific order of facets
* a facet can contains multiple values

Define a query path would not work in this case.

The solution is to define a part of a query path and attach the facet you want to place inside of the url.

This is done by separating the query path and the facet by a dash.

.. code-block:: yaml

  routeEnhancers:
    products:
      # Note: All arguments inside of namespace tx_solr. See -> extensionKey
      # Example: Argument 'type' define as 'filter-type' will convert into 'tx_solr/filter-type'
      _arguments:
        productType: filter-productType
      requirements:
        productType: '.*'

The route enhancer now knows to place values of facet `productType` as path segment.

As result the URL now changed into

	/products/candy/?tx_solr[filter][0]=taste:matcha&tx_solr[filter][1]=color:yellow&tx_solr[filter][2]=color:green&tx_solr[filter][3]=taste:sweet

.. important::
   The name used as argument name have to match the name of the Solr filter.

.. important::
   We recommend to configured the requirements for a variable with `.*`. If a value is required it will lead to an exception if not type is omitted.


Handling of multiple values
---------------------------

As the customer can combine as many values of a facet to filter the result, the path segment can contain multiple value.

The route enhancer will collect all values of the configured facet and combine them to a single string.

The values will sort alphanumeric and concat with a colon.

You can change the value separator by configuring a multi value separator:

.. code-block:: yaml

  routeEnhancers:
    products:
      solr:
        multiValueSeparator: ';'

Additional you can replace specific characters inside of each value using a map to replace the characters:

.. code-block:: yaml

  routeEnhancers:
    products:
      solr:
        replaceCharacters:
          ' ': '-'

.. important::
  If you replace characters, please take care that the target character is not a character, which could be a part of the facet value.

Now lets have a look at the effect.

Before the configuration we had this URL:

	/products/?tx_solr[filter][0]=taste:matcha&tx_solr[filter][1]=productType:dark+chocolate&tx_solr[filter][2]=taste:sweet&tx_solr[filter][3]=productType:candy

After applying the configuration, the URL changed into:

	/products/candy;dark-chocolate?tx_solr[filter][0]=taste:matcha&tx_solr[filter][1]=taste:sweet

Full example
------------

This example shows the all configuration done above

.. code-block:: yaml

  routeEnhancers:
    products:
      type: CombinedFacetEnhancer
      limitToPages:
        - 42
      extensionKey: tx_solr
      routePath: '/{productType}'
      # Note: All arguments inside of namespace tx_solr. See -> extensionKey
      # Example: Argument 'type' define as 'filter-type' will convert into 'tx_solr/filter-type'
      _arguments:
        productType: filter-productType
      # Important: Configure requirement for fields! If you wand to allow empty values, set .*
      requirements:
        productType: '.*'
      solr:
        multiValueSeparator: ';'
        replaceCharacters:
          ' ': '-'
