ConnectionManager
=================

In EXT:solr all the configuration, including options affecting backend functions, are done in TypoScript. The clear cache menu provides an entry to initialize the Solr connections configured in TypoScript.

How it works
------------

* Configure the Solr connection in TypoScript under plugin.tx_solr.solr, providing host, port, and path.
* On your site's root page set the flag Use as Root Page on the Behaviour tab.
* Initialize the Solr connections through the clear cache menu

.. figure:: ../Images/GettingStarted/typo3-initialize-connections.png

    Initialize all solr connections

When initializing the Solr connections the extensions looks for all the pages with the root flag set, generates the TypoScript configuration for that page like in the frontend and reads the Solr connection parameters.

The extension also repeats that process for each language configured on the installation's root (uid = 0). This way you can configure different Solr cores for each language by using regular conditions that change the path of the Solr connection depending on the currently selected language.

Once all the configured Solr connections in the installation are found, they're stored in TYPO3's registry so that they can easily be retrieved without needing to reevaluate the TypoScript configuration every time we connect to Solr.

All that magic happens in class source:Classes/ConnectionManager.php. The connection manager and it's public API actually must be used whenever a Solr connection is needed.