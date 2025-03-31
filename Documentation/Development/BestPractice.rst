=============
Best Practice
=============

Optimize Page Indexing
======================

Indexing of `pages` is usually done via this extensions `PageIndexer` which
determines a pages content by crawling its URL and extracting everything between
the two magic markers `<!--TYPO3SEARCH_begin-->` and `<!--TYPO3SEARCH_end-->`.
When fetching the URL the header `X-Tx-Solr-Iq` is added to the request, which
is (among other things) disabling all caches.

To reduce crawling runtimes you might wanna disable the rather expensive
generation of navigation structures such as main and footer menus that you've
defined for your `page` object in TypoScript.

You can do so by creating a TypoScript condition that unsets all sorts of
non-content elements. Like this for example:

.. code-block:: typoscript

    [request && traverse(request.getHeaders(), 'x-tx-solr-iq/0')]
    page.10.dataProcessing >
    page.10.variables >
    page.10.variables {
        content < styles.content.get
        content.select.where = {#colPos}=0
        contentMarginal < styles.content.get
        contentMarginal.select.where = {#colPos}=1
    }
    [global]

You should notice a significant difference right away:


.. code-block:: bash

    bin/typo3 scheduler:run --task=<tast-id-for-site> --force

or if EXT:solrconsole is installed on your system:

.. code-block:: bash

    bin/typo3 solr:queue:index -n -a 1 -s <site>

If not: go to the TYPO3 backend and check the `page` object definition in the
TypoScript object browser with that condition being activated to see what it
looks like now.
