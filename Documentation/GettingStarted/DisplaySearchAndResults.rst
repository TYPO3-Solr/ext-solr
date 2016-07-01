.. _started-display-results:

Display search and results
==========================

After Solr has some documents inside his index, you can insert the plugin to provide a search with
results from Solr. To do so create a new content record of type *Search* on a page:

.. image:: /Images/GettingStarted/typo3-insert-plugin-1.png

Select *Search: Form, Result, Additional Components* if not already selected inside the content
element:

.. image:: /Images/GettingStarted/typo3-insert-plugin-2.png

Open the page and search for ``*``, you should see all currently indexed records from Solr:

.. image:: /Images/GettingStarted/typo3-first-result.png

That's it. You now have a working TYPO3 Installation with Solr integration. You are able to queue
items for indexing, index them and provide an interface for visitors to search the indexed records.

You can now start to adjust the templates according to :ref:`tx_solr.templateFiles`, configure
indexing or searching and facets.
