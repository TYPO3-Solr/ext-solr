.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _conf-logging:


.. raw:: latex

    \newpage

.. raw:: pdf

   PageBreak

================================================================
7.5.0 - Maintenance release - Apache Solr 6.6.2 security release
================================================================

This release is a bugfix only release. It contains all patches from 6.1.3 + the update to Apache Solr 6.6.2.
This release is for everyone who needs to stay on TYPO3 7 LTS, if you are using 8 LTS you should update to 7.5.0 when possible.

Update to Apache Solr 6.6.2
===========================

There was a zero day exploit discovered in Apache Solr that allows to load external entities by manipulating the doctype of the response. The Apache Solr release 6.6.2 fixes this issue. This patch add's the support for Apache Solr 6.6.2 and updates the docker container to be based on Apache Solr 6.6.2.

During the update you should update your Apache Solr server to the shipped and supported version 6.6.2 by using the shipped docker container or using your own install process for apache solr. Just updating the extension is not enough.

* https://github.com/TYPO3-Solr/ext-solr/pull/1697
* https://cve.mitre.org/cgi-bin/cvename.cgi?name=CVE-2017-12629
* https://issues.apache.org/jira/browse/SOLR-11477

Backport phpunit related changes
================================

A change in phpunit required to update the testcases. This fix was backported to the 7.5.x branch to be able to run all tests.

* https://github.com/TYPO3-Solr/ext-solr/pull/1696

Contributors
============

Big thanks to our partners that have joined the EB2017 program:

* .hausformat
* AGENTUR FRONTAG AG
* Agentur rootfeld
* amarantus - media design & conding Mario Drengner & Enrico Nemack GbR
* Amedick & Sommer Neue Medien GmbH
* Andrea Pausch
* Animate Agentur für interaktive Medien GmbH
* Arrabiata Solutions GmbH
* ARBURG GmbH & Co KG
* artig GmbH & Co. KG
* BAK Basel Economics AG
* b:dreizehn GmbH
* BIBUS AG Group
* Bitmotion GmbH
* cab services ag
* Causal Sarl
* CHIARI GmbH
* Citkomm services GmbH
* clickstorm GmbH
* Connecta AG
* Cows Online GmbH
* Creative360
* cron IT GmbH
* CYBERhouse Agentur für interaktive Kommukation GmbH
* cyperfection GmbH
* data-graphis GmbH
* Département de Maine-et-Loire
* Deutsche Welthungerhilfe e.V.
* Deutscher Ärzteverlag
* Deutscher Volkshochschul-Verband
* Die Medialen GmbH
* die_schnittsteller gmbh
* Dörfer engineering services
* E-Magineurs
* EYE Communications AG
* Fachhochschule für öffentliche Verwaltung NRW Zentralverwaltung Gelsenkirchen
* familie redlich AG
* Fork Unstable Media GmbH
* hauptsache.net GmbH
* Havas Düsseldorf GmbH
* Hirsch & Wölfl GmbH
* Hochschule Furtwangen - IMZ Online Services
* Hochschule Konstanz
* Institut der deutschen Wirtschaft Köln Medien GmbH
* Inter Krankenversicherungen AG
* iresults gmbh
* ITK Rheinland
* itl Institut für technische Literatur AG
* jweiland.net
* Kassenärztliche Vereinigung Rheinland-Pfalz
* Kerstin Nägler Web & Social Media Beratung
* Landesinstitut für Schule und Medien Berlin-Brandenburg
* Leibniz Universität IT Services
* Libéo
* Lime Flavour GbR
* LINGNER CONSULTING NEW MEDIA GMBH
* LOUIS INTERNET
* Maximilian Walter
* MEDIA:ESSENZ
* mehrwert intermediäre kommunikation GmbH
* Mercedes-AMG GmbH
* Medidium Technologies
* mlm media process management GmbH
* n@work Internet Informationssystems GmbH
* Netcreators
* netz-haut GmbH
* neuwerk interactive
* Nintendo of Europe GmbH
* Onedrop Solutions GmbH
* Open New Media GmbH
* Paints Multimedia GmbG
* pixelcreation GmbH
* plan2net
* Pluswerk AG
* polargold GmbH
* punkt.de GmbH
* Raiffeisen OnLine GmbH
* Roza Sancken
* ruhmesmeile GmbH
* Rundfunk und Telekom Regulierung GmbH
* Schweizer Alpen-Club SAC
* Sebastian Schreiber
* sitegeist media solutions GmbH
* Somedia Production AG
* Star Finanz-Software Entwicklung und Vertriebs GmbH
* Stefan Galinski Internetdienstleistungen
* Stratis - Toulon
* Studio Mitte Digital Media GmbH
* Studio 9 GmbH
* Systime A/S
* SYZYGY Deutschland GmbH
* takomat Agentur GbR
* THE BRETTINGHAMS GmbH
* TOUMORO
* Triplesense Reply GmbH
* TWT Interactive GmbH
* Typoheads GmbH
* unternehmen online GmbH & Co. KG
* Universität Bremen
* VERDURE Medienteam GmbH
* visol digitale Dienstleistungen GmbH
* WACON Internet GmbH
* webedit AG
* Webstore GmbH
* Webtech AG
* wegewerk GmbH
* WIND Internet
* Wohnungsbau- und Verwaltungsgesellschaft mbH Greifswald
* XIMA MEDIA GmbH
* zdreicom GmbH
* zimmer7 GmbH

Thanks to everyone who helped in creating this release!

How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us in 2017 by becoming an EB partner:

http://www.typo3-solr.com/en/contact/

or call:

+49 (0)69 - 2475218 0