:orphan:

.. _started-legacy-site-mode:

Extension Configuration for legacy site mode
--------------------------------------------

.. warning::

   This variant of configuration is outdated and will not work with enabled `"Site Handling" <https://docs.typo3.org/m/typo3/reference-coreapi/9.5/en-us/ApiOverview/SiteHandling/Basics.html />`_.

To enable Solr with legacy site configuration(TypoScript, conditions and co.) the "Legacy Site Mode" in Extension configuration under Backend "Settings":>"Extension Configuration":>"solr" must be enabled.
After that you can proceed with configuration via TypoScript.

   .. warning::

      This setting will enable globally for the TYPO3 instance and can not be enabled\/disabled per Site.

.. image:: ../Images/GettingStarted/LegacySiteMode_extension_settings.jpg

TypoScript
----------

Update the constants to match the current setup:

.. code-block:: typoscript

    plugin {
        tx_solr {
            solr {
                read {
                    host = 192.168.99.100
                    port = 8983
                }
                write < .read
            }
        }
    }

Adjust the host according to where your Solr is reachable, see :ref:`started-solr`.

**Note:**

The static template configures what you need to query the solr server and do the indexing.
In most projects you want to add facets or custom styles. If you want to use the default style you need to add
the template "Search - Default Stylesheets". Beside that EXT:solr provides a few example typoscript templates that should
help you to build your own configuration.

Domain Records and Indexing
---------------------------

To enable Solr connections, the extension needs a Domain Record and indexing has to be enabled.
Therefore enable indexing by setting the following TypoScript:

.. code-block:: typoscript

    config {
        index_enable = 1
    }

Also define that your root page is actually a root page:

.. image:: /Images/GettingStarted/typo3-root-page.png

Last but not least, add the domain record to the root page:

.. image:: /Images/GettingStarted/typo3-domain-record.png

Initialize Solr Connection
---------------------------

Next, initialize the Solr Connection from TYPO3 and check whether everything works as expected.

To initialize the connection, open the Cache-Menu and start Initialization.

.. image:: /Images/GettingStarted/typo3-initialize-connections.png

Check whether connections to Solr could be established by opening the *Reports* module and go to
*Status Report* view:

.. image:: /Images/GettingStarted/typo3-check-connections.png

That's it, head over to :ref:`started-index`.
