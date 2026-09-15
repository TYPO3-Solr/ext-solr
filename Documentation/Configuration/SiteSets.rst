.. _configuration-site-sets:

Site Sets
=========

.. versionadded:: 14.0

   EXT:solr provides TYPO3 site sets in :file:`Configuration/Sets/`.

TYPO3 site sets are the recommended way to provide reusable site-scoped
configuration in TYPO3 14 projects. EXT:solr ships site sets for the base
configuration, optional frontend assets, OpenSearch and the example
configurations that were previously available as static TypoScript includes.

If your project does not use TypoScript template records
(``sys_template``), add EXT:solr configuration through site set
dependencies. TYPO3 resolves, orders and deduplicates site set dependencies,
which makes this approach more robust than managing dependencies manually in
TypoScript records.

Example site configuration:

.. code-block:: yaml
   :caption: config/sites/my-site/config.yaml

   dependencies:
     - apache-solr-for-typo3/solr
     - apache-solr-for-typo3/solr-stylesheets

TypoScript template records are still supported for existing installations and
mixed setups. When using site sets, prefer dependencies between sets instead
of cross-extension TypoScript imports or duplicated static includes.

Available Site Sets
-------------------

The following site sets are provided by EXT:solr:

.. list-table::
   :header-rows: 1
   :widths: 45 55

   * - Site set
     - Description
   * - ``apache-solr-for-typo3/solr``
     - Base Configuration
   * - ``apache-solr-for-typo3/solr-bootstrap-css``
     - Bootstrap CSS Framework
   * - ``apache-solr-for-typo3/solr-stylesheets``
     - Default Stylesheets
   * - ``apache-solr-for-typo3/solr-open-search``
     - OpenSearch
   * - ``apache-solr-for-typo3/solr-example-ajaxify``
     - Ajaxify the search results
   * - ``apache-solr-for-typo3/solr-example-boost-queries``
     - Boost more recent results
   * - ``apache-solr-for-typo3/solr-example-everything-on``
     - Everything On
   * - ``apache-solr-for-typo3/solr-example-filter-pages``
     - Filter to only show page results
   * - ``apache-solr-for-typo3/solr-example-suggest``
     - Suggest/autocomplete
   * - ``apache-solr-for-typo3/solr-example-facets-options``
     - Options facet on author field
   * - ``apache-solr-for-typo3/solr-example-facets-options-toggle``
     - Options with on/off toggle
   * - ``apache-solr-for-typo3/solr-example-facets-options-prefix-grouped``
     - Options grouped by prefix
   * - ``apache-solr-for-typo3/solr-example-facets-options-single-mode``
     - Options with singlemode (only one option at a time)
   * - ``apache-solr-for-typo3/solr-example-facets-options-filtered``
     - Options filterable by option value
   * - ``apache-solr-for-typo3/solr-example-facets-query-group``
     - QueryGroup facet on the created field
   * - ``apache-solr-for-typo3/solr-example-facets-hierarchy``
     - Hierarchy facet on the rootline field
   * - ``apache-solr-for-typo3/solr-example-facets-date-range``
     - DateRange facet with datepicker on created field
   * - ``apache-solr-for-typo3/solr-example-numeric-range``
     - NumericRange facet with slider on pid field
   * - ``apache-solr-for-typo3/solr-example-type-field-group``
     - Fieldgroup on type field
   * - ``apache-solr-for-typo3/solr-example-pid-query-group``
     - Querygroup on pid field
   * - ``apache-solr-for-typo3/solr-example-index-queue-news``
     - Index Queue Configuration for news
   * - ``apache-solr-for-typo3/solr-example-index-queue-news-content-elements``
     - Index Queue Configuration for news with content elements
