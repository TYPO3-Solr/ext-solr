.. highlight:: typoscript

.. _started-configure-extension:

Configure Extension
===================

After :ref:`started-install-extension` you need to configure the extension. Only a few steps from below are necessary for :ref:`started-index`.

Static TypoScript
-----------------

The extension already comes with basic configuration that will work for small pages out of the box. For now create or
edit an existing TypoScript Template record in your page tree and add the provided static TypoScript:

.. image:: ../Images/GettingStarted/typo3-include-static-typoscript.png

Search Markers
--------------

EXT:solr is indexing everything on a page between `<!-- TYPO3SEARCH_begin -->` and `<!-- TYPO3SEARCH_end -->` to ensure this is the case, check the output of you website and add the markers to your template.

If the markers are missing, you should add them to your template. To increase the quality of the search results the markes should only wrap the relevant content of a page and exclude e.g. menus, because they are same on each page.
The markers can be used multiple times, but make sure each "TYPO3SEARCH_begin" is properly closed with "TYPO3SEARCH_end".

The most simple configuration for my page was:


.. code-block:: typoscript

    page.10 {
        stdWrap.dataWrap = <!--TYPO3SEARCH_begin-->|<!--TYPO3SEARCH_end-->
    }

Site Handling or Legacy site mode
---------------------------------

*There are two ways to setup EXT:Solr on your site.*

.. tip::

   The "Site Handling mode" is the preferred way for configuring current EXT:Solr and it is only usable,
   if you are able to enable `new site handling feature <https://docs.typo3.org/m/typo3/reference-coreapi/9.5/en-us/ApiOverview/SiteHandling/Basics.html>`__ for all your sites.

.. warning::

   The "Legacy site mode" in EXT:solr 10.0 is kept for backwards compatibility for users, which have had upgraded to TYPO3 9.5 LTS
   but do not want to use the `new site handling feature <https://docs.typo3.org/m/typo3/reference-coreapi/9.5/en-us/ApiOverview/SiteHandling/Basics.html>`__ currently.


Site Handling (recommended)
~~~~~~~~~~~~~~~~~~~~~~~~~~~

In TYPO3 9.5 the new backend module called "Sites" was introduced.
This Module can be used to setup and configure the Apache Solr connections.

Legacy site mode (not recommended)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. warning::

   The ability to use this mode will be removed in EXT:solr 11.0.

To follow the steps for this mode please refer to :doc:`Extension Configuration for legacy site mode <ConfigureExtensionLegacySiteMode>`

