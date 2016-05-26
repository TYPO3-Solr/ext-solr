.. highlight:: typoscript

.. _started-configure-extension:

Configure Extension
===================

After :ref:`started-install-extension` you need to configure the extension. The extension already
comes with basic configuration that will work for small pages out of the box. For further
configuration options head over to :ref:`conf-index` once everything is setup.

For now create, or edit an existing, TypoScript Template record in your page tree and add the
provided static TypoScript:

.. image:: /Images/GettingStarted/typo3-include-static-typoscript.png

Update the constants to match the current setup::

    plugin {
        tx_solr {
            solr {
                host = 192.168.99.100
                port = 8282
            }
        }
    }

Adjust the host according to where your Solr is reachable, see :ref:`started-solr`.

Next, initialize the Solr Connection from TYPO3 and check whether everything works as expected.

To initialize the connection, open the Cache-Menu and start Initialization.

.. image:: /Images/GettingStarted/typo3-initialize-connections.png

Check whether connections to Solr could be established by opening the *Reports* module and go to
*Status Report* view:

.. image:: /Images/GettingStarted/typo3-check-connections.png

That's it, head over to :ref:`started-index`.
