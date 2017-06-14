=========
Languages
=========

We recommend to create one solr core per language. The shipped solr example configuration provides a setup for the following languages:

* Arabic
* Armenian
* Basque
* Brazilian portuguese
* Bulgarian
* Burmese
* Catalan
* Chinese
* Czech
* Danish
* Dutch
* English
* Finnish
* French
* Galician
* German
* Greek
* Hindi
* Hungarian
* Indonesian
* Italian
* Japanese
* Khmer
* Korean
* Lao
* Norwegian
* Persian
* Polish
* Portuguese
* Romanian
* Russian
* Spanish
* Swedish
* Thai
* Turkish
* Ukrainian

The configuration of the connection between solr cores and sites is done in typoscript.

The following typoscript snipped shows an example how to configure multiple languages for the introduction package (EN, DE and DA):


.. code-block:: typoscript

    plugin.tx_solr.solr {
       scheme = http
       port   = 8082
       path   = /solr/core_en/
       host   = localhost
    }

    [globalVar = GP:L = 1]
    plugin.tx_solr.solr.path = /solr/core_de/
    [end]

    [globalVar = GP:L = 2]
    plugin.tx_solr.solr.path = /solr/core_da/
    [end]


After setting up the languages with typoscript you need to initialize the solr connections with the connection manager (:ref:`connection-manager`).
