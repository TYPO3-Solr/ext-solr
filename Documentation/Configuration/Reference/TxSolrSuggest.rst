.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _conf-tx-solr-suggest:

tx_solr.suggest
===============

This feature allows you to show a suggest layer that suggest terms that start with the letters that have been typed into the search field


numberOfSuggestions
-------------------

:Type: Integer
:TS Path: plugin.tx_solr.suggest.numberOfSuggestions
:Since: 1.1
:Default: 10

Sets the number of suggestions returned and displayed in the layer attached to the search field.

suggestField
------------

:Type: String
:TS Path: plugin.tx_solr.suggest.suggestField
:Since: 1.1
:Default: spell

Sets the Solr index field used to get suggestions from. A general advice is to use a field without stemming on it. For practical reasons this is currently the spell checker field.

forceHttps
----------

:Type: Boolean
:TS Path: plugin.tx_solr.suggest.forceHttps
:Since: 1.1
:Default: 0
:Options: 0,1

If enabled, HTTPS will be used for querying suggestions. Otherwise HTTP will be used.

treatMultipleTermsAsSingleTerm
------------------------------

:Type: Boolean
:TS Path: plugin.tx_solr.suggest.treatMultipleTermsAsSingleTerm
:Since: 1.4 / 1.7-dkd
:Default: 0
:Options: 0,1

When a user types multiple words into your search field they usually are split up into full keywords used in the query's q parameter and the last part being the partial keyword in the facet.prefix parameter. Enabling this setting moves everything into the facet.prefix parameter. This is usually only useful when using a string field as suggest / auto-complete source field.

Example - Here "Hello Solr" are the full keywords and the user started typing "World" so that "Wo" is used as the partial keyword:

"Hello Solr Wo" -> q=Hello Solr, facet.prefix=Wo (default)
"Hello Solr Wo" -> q=<empty>, facet.prefix=Hello Solr Wo (treatMultipleTermsAsSingleTerm)

showTopResults
--------------

:Type: Boolean
:TS Path: plugin.tx_solr.suggest.showTopResults
:Since: 8.0
:Default: 1

When this setting is enabled, the top results are shown in the suggest layer. The top results are build from the first search match,
or when the first search delivers no hit, the results from the first suggestion are shown.

numberOfTopResults
------------------

:Type: Integer
:TS Path: plugin.tx_solr.suggest.numberOfTopResults
:Since: 8.0
:Default: 5

Defines the number of top results that will be shown.

additionalTopResultsFields
--------------------------

:Type: String
:TS Path: plugin.tx_solr.suggest.additionalTopResultsFields
:Since: 9.0

Comma-separated list of fields that should be added to the top results response json.


