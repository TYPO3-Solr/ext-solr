=========
Languages
=========

We recommend to create one solr core per language. The shipped solr example configuration provides a setup for the following languages:

* Arabic (core_ar)
* Armenian (core_hy)
* Basque (core_eu)
* Brazilian portuguese (core_ptbr)
* Bulgarian (core_bg)
* Burmese (core_my)
* Catalan (core_ca)
* Chinese (core_zh)
* Czech (core_cs)
* Danish (core_da)
* Dutch (core_nl)
* English (core_en)
* Finnish (core_fi)
* French (core_fr)
* Galician (core_gl)
* German (core_de)
* Greek (core_el)
* Hindi (core_hi)
* Hungarian (core_hu)
* Indonesian (core_id)
* Irish (core_ie)
* Italian (core_it)
* Japanese (core_ja)
* Khmer (core_km)
* Korean (core_ko)
* Lao (core_lo)
* Latvia (core_lv)
* Norwegian (core_no)
* Persian (core_fa)
* Polish (core_pl)
* Portuguese (core_pt)
* Romanian (core_ro)
* Russian (core_ru)
* Serbian (core_rs)
* Spanish (core_es)
* Swedish (core_sv)
* Thai (core_th)
* Turkish (core_tr)
* Ukrainian (core_uk)

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
