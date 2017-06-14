===========
Autosuggest
===========

A user of the search typically want to find the results a fast as possible. To support the user and avoid to much typing
solr can create a drop down list of common suggested search terms right after the search input box.

This feature can be easily configured with the following typoscript setting:

.. code-block:: typoscript

    plugin.tx_solr.search {
        suggest = 1
        suggest {
            numberOfSuggestions = 10
            suggestField = spell
        }
    }

Beside the server related part solrfluid ships the jQueryUi autocomplete implementation to show the suggest results.
If you want to configure an the autosuggest by example, you can include the typoscript example template **"(Example) Suggest/autocomplete with jquery ui"**.

When everything is configured the frontend will show you a drop down of suggestions when you are typing in the search field:

.. figure:: ../Images/Frontend/Autosuggest/autosuggest.png

    Autocomplete with jQuery ui