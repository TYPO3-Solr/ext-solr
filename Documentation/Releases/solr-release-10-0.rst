.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _releases-10:

============================
Apache Solr for TYPO3 10.0.0
============================

We are happy to release EXT:solr 10.0.0.
The focus of EXT:solr 10.0.0 was, to support the `site handling <https://docs.typo3.org/m/typo3/reference-coreapi/9.5/en-us/ApiOverview/SiteHandling/Index.html />`_ that was introduced in TYPO3 9 LTS .

**Important**: This version is installable with TYPO3 9 LTS only.

Since the development budget is limited at one side and we have other project requests at the other side we could spend less time on the development of EXT:solr.
If you want to support us please consider to sponsor us.

New in this release
===================

TYPO3 9 LTS site handling support
---------------------------------

The major new feature of EXT:solr 10 is the support of the TYPO3 site handling. You can now configure the Apache Solr connections along with your TYPO3 site.
To get a typical solr site running you now need only a few settings and can use the site handling user interface for that.

Apache Solr 8.2 support
-----------------------

EXT:solr 10 ships Apache Solr 8.2.

Note: The data volume of the Apache Solr Docker container was changed from ```/opt/solr/server/solr/data``` to ```/var/solr/data/data``` this might require changes in your infrastructure configuration.

Extensionscanner cleanup
------------------------

Most (not all) of the extension scanner violations have been fixed. We hope to get the extension scanner violations cleaned in the next releases.

Drop TYPO3 8 compatibility
--------------------------

To simplify the development we've dropped the compatibility for TYPO3 8 LTS. If you need to use TYPO3 8 please use the 9.0.x branch.


Add default field for image and price
-------------------------------------

To allow external applications to index common information for product's we've added a field for price and image. Along with that we've changed the suggest to render the content of the "image" field instead of "previewImage_stringS", this might require changes in  your index configuration.


Migration from EXT:solr 9 to EXT:solr 10
========================================

Important things on sites with activated Site Handling
------------------------------------------------------

By default EXT:solr 10 expects the configuration of solr connections and cores in the site handling module, along with your TYPO3 site.
The configuration of the solr connections with your site are available immediatly, so now initialization of connections is required anymore.

The old fashioned setup (domain records and solr connections in TypoScript) are now the *legacy mode*. If you want or need to use that still, you can enable the legacy mode,
with your extension configuration by setting ```allowLegacySiteMode = 1```.

The following things will become standard, and should be preferred and activated/configured as close as possible and in some cases immediately,
otherwise the things wil not work or break the whole setup:

TypoScript
~~~~~~~~~~

plugin.tx_solr.solr
"""""""""""""""""""

This TypoScript configurations for Constants and for Setup are at least partially obsolete and are ignored on Site Handling activated sites.
All Apache Solr connections must be stored in Site Handling "config.yaml" file for each language.

config.absRefPrefix
"""""""""""""""""""

The `"config.absRefPrefix" <https://docs.typo3.org/m/typo3/reference-typoscript/master/en-us/Setup/Config/Index.html#absrefprefix />`_ is `obsolete <https://docs.typo3.org/c/typo3/cms-core/9.5/en-us/Changelog/9.4/Feature-86057-ImprovedTypolinkURLLinkGeneration.html />`_ and must be replaced with
Site Handlings `"base" <https://docs.typo3.org/m/typo3/reference-coreapi/9.5/en-us/ApiOverview/SiteHandling/Basics.html#base />`_ or `"baseVariants" <https://docs.typo3.org/m/typo3/reference-coreapi/9.5/en-us/ApiOverview/SiteHandling/BaseVariants.html />`_ settings.


Limitations of the site UI and yaml configuration
-------------------------------------------------

*Important:* The goal of the configuration with your TYPO3 site and the site module UI was to simplify the setup, especially for new users. Therefore, not all options are still possible as before, but the most of them are still possible by editing the yaml file.

There are the following known and wanted limitations:

* It is **not** possible to configure a username and a password for the solr server with the UI. You still have the possibility to add that configuration in the yaml file, in that case make sure that this file is not readable from the web!. Another approach is to configure the usage from the environment variables. To configure a username the setting ```solr_username_read``` or ```solr_password_read```
can be used in the yaml file.

* It is **not** possible to configure a different solr hostname with the UI. If you need that you can still configured that in the yaml file, by using the fallback mechanism.

The fallback mechanism work like that:

Each setting has the following structure ``solr_{$setting}_{$scope}"```. The scope can be read or write. Every setting can be overwritten for the scope *write*, if nothing is configured it will fallback to the *read* setting. Every setting can be defined on the language level, if it is not configured on the language level it fallsback to the global setting.

Example:

::

    base: 'http://solr-ddev-site.ddev.site/'
    baseVariants: {  }
    errorHandling: {  }
    languages:
      -
        title: English
        enabled: true
        base: /
        typo3Language: default
        locale: en_US.UTF-8
        iso-639-1: en
        navigationTitle: ''
        hreflang: en-US
        direction: ''
        flag: global
        solr_host_read: solr_node_1
        solr_core_read: core_en
        languageId: '0'
      -
        title: German
        enabled: true
        base: /de/
        typo3Language: de
        locale: de_DE.UTF-8
        iso-639-1: de
        navigationTitle: ''
        hreflang: de-DE
        direction: ''
        flag: global
        solr_host_read: solr_node_2
        solr_core_read: core_de
        languageId: '1'
    rootPageId: 3
    routes: {  }
    solr_enabled_read: true
    solr_path_read: /solr/
    solr_port_read: 8983
    solr_scheme_read: http
    solr_use_write_connection: false

::

The example above shows that you are able to define the setting ```solr_host_read``` on the language level. Since this is a more advanced configuration and the user interface should be kept simple, this can only be configured in the yaml.

Removed Code
============

The following code parts have been removed as announced in previous versions of EXT:solr:

TBD

Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Achim Fritz
* Georg Ringer
* Helmut Hummel
* Marc Bastian Heinrichs
* Marco Pfeiffer
* Markus Kobligk
* Netcoop
* Nicole Cordes
* Rafael Kähm
* Rémy DANIEL
* Sascha Egerer
* Stefan Frömken
* Stephan Jorek
* Timo Hund
* Yann Weyer
* Gerald Aistleitner

Also a big thanks to our partners that have joined the EB2019 program:

* 21TORR GmbH
* 3m5, Media GmbH
* Absolut Research GmbH
* AgenturWebfox GmbH
* Amedick & Sommer Neue Medien GmbH
* arndtteunissen GmbH
* Arrabiata Solutions GmbH
* artif GmbH & Co. KG
* Atol Conseils & Développements
* b13 GmbH
* bgm business websolutions GmbH & Co KG
* Bitmotion GmbH
* BIBUS AG Group
* Bitmotion GmbH
* Columbus Interactive GmbH
* Consulting Piezunka und Schamoni - Information Technologies GmbH
* cosmoblonde GmbH
* CS2 AG
* datamints GmbH
* Diesel Technic AG
* Die Medialen GmbH
* Direction des Systèmes d’Information - Département du Morbihan
* dörler engineering services
* E-Magineurs
* Fachhochschule für öffentliche Verwaltung NRW Zentralverwaltung
* fixpunkt werbeagentur gmbh
* Flowd GmbH
* Frequentis Comsoft GmbH
* GAYA - La Nouvelle Agence
* Gernot Leitgab
* Getdesigned GmbH
* .hausformat GmbH
* Haute école de travail social et de la santé - EESP
* Hirsch & Wölfl GmbH
* Hochschule Furtwangen
* Hypo Tirol Bank AG
* Intera Gesellschaft für Software-Entwicklung mbH
* interactive tools GmbH - Agentur für digitale Medien
* internezzo ag
* iresults gmbh
* ITK Rheinland
* LOUIS INTERNET GmbH
* Kassenärztliche Vereinigung Bayerns (KZVB)
* KONVERTO AG
* kraftwerk Agentur für neue Kommunikation GmbH
* Landesinstitut für Schule und Medien Berlin-Brandenburg
* Libéo
* LINGNER CONSULTING NEW MEDIA GMBH
* MaxServ B.V.
* McLicense GmbH
* MeinEinkauf AG
* NEW.EGO GmbH
* medien.de mde GmbH
* mehrwert intermediale kommunikation GmbH
* mellowmessage GmbH
* mentronic . Digitale Kommunikation
* MOSAIQ GmbH
* pietzpluswild GmbH
* plan2net GmbH
* plan.net - agence conseil en stratégies digitales
* Proud Nerds
* +Pluswerk AG
* punkt.de GmbH
* Redkiwi
* ressourcenmangel dresden GmbH
* rrdata
* RKW Rationalisierungs- und Innovationszentrum der Deutschen Wirtschaft e.V.
* Site’nGo
* SIWA Online GmbH
* Stadt Wien - Wiener Wohnen Kundenservice GmbH
* Stadtverwaltung Villingen-Schwenningen
* Stefan Galinski Internetdienstleistungen
* Studio Mitte Digital Media GmbH
* TOUMORO
* Ueberbit Gmbh
* WACON Internet GmbH
* webconsulting business services gmbh
* webschuppen GmbH
* Webstobe GmbH
* webit! Gesellschaft für neue Medien mbH
* wegewerk GmbH
* werkraum Digitalmanufaktur GmbH
* XIMA MEDIA GmbH

Special thanks to our premium EB 2019 partners:

* jweiland.net
* sitegeist media solutions GmbH

How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us by becoming an EB partner:

http://www.typo3-solr.com/en/contact/

or call:

+49 (0)69 - 2475218 0


