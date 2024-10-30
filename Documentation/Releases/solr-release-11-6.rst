..  include:: /Includes.rst.txt
..  index:: Releases
.. _releases-11-6:

==============
Release 11.6.0
==============

We are happy to release EXT:solr 11.6.0.
The focus of this release has been on Apache Solr upgrade to v9.7.0.

**Important**: This version is installable with TYPO3 11 LTS on v11.5.14+ only and contains some breaking changes, see details below.

New in this release
-------------------

!!! Upgrade to Apache Solr 9.7.0
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

This release requires Apache Solr v9.7.0+.

Along with the compatibility to Solr 9.7 the dependency to SOLR_ENABLE_STREAM_BODY is removed.

Full list of changes
~~~~~~~~~~~~~~~~~~~~

* !!![BUGFIX:BP:11.6] Queue check considers indexing configuration by @dkd-friedrich
* !!![TASK:BP:11.6] Introduce queue and queue item interfaces by @dkd-friedrich
* [TASK:BP:11.6] Consider queue initialization status by @dkd-friedrich
* !!![TASK:BP:11.6] Introduce specific EXT:solr exceptions  by @dkd-friedrich
* [TASK] Prepare release-11.6.x for TYPO3 11.5 LTS by @dkd-kaehm
* [TASK] sync the CI stuff from main branch into 11.6.x by @dkd-kaehm
* [TASK] Activate PHPStan  by @dkd-friedrich
* [TASK] Use Apache Solr 9.2 for EXT:solr 11.6  by @dkd-friedrich
* [FEATURE] Introduce TYPO3_SOLR_ENABLED_CORES docker env variable by Christoph Lehmann
* [TASK] Make it possible to change solr unix GID:UID on docker image… by @dkd-kaehm
* [TASK] revert unnecessary changes on Apache Solr 9.2 upgrade by @dkd-kaehm
* !!![TASK] Upgrade to Apache Solr 9.3.0 by @dkd-friedrich
* [BUGFIX] Fix result highlighting fragment size by @dkd-friedrich
* [TASK] Update to Solr 9.5 by @dkd-friedrich
* New Crowdin updates 2024.07.03 by @dkd-kaehm
* [TASK] Upgrade to Apache Solr 9.7 by @dkd-friedrich


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Christoph Lehmann
* Markus Friedrich
* Rafael Kähm

Also a big thank you to our partners who have already concluded one of our new development participation packages such as Apache Solr EB for TYPO3 11 LTS:

* .hausformat GmbH
* 3m5. Media GmbH
* abteilung_digital GmbH
* ACO Ahlmann SE & Co. KG
* Agence E-magineurs
* Agenda d.o.o.
* AgenturWebfox GmbH
* Amedick & Sommer Neue Medien GmbH
* Ampack AG
* Atol CD
* Autorité des Marchés Financiers (Québec)
* avenit AG
* b13 GmbH
* brandung GmbH Oliver Krause
* Bundesanstalt Statistik Österreich
* Bytebetrieb GmbH & Co. KG
* Canton de Neuchâtel - SIEN
* CARL von CHIARI GmbH
* chiliSCHARF GmbH
* clickstorm GmbH
* co-operate Wegener & Rieke GmbH
* Columbus Interactive GmbH
* cosmoblonde GmbH
* creativ clicks GmbH
* cyperfection GmbH
* digit.ly
* DMK E-BUSINESS GmbH
* Earlybird GmbH & Co KG
* elancer-team GmbH
* eulenblick Kommunikation und Werbung
* Fa. .hausformat
* Fa. Ausy
* Fachagentur Nachwachsende Rohstoffe fnr.de
* Fachhochschule Erfurt
* Fourdegrees GbR
* FTI Touristik GmbH
* für novotegra GmbH (BayWa r.e. AG)
* Getdesigned GmbH
* GFE Media GmbH
* graphodata GmbH
* gressourcenmangel integral gmbh
* grips IT GmbH
* hiroki digital GmbH
* Hirsch & Wölfl GmbH
* Hochschule Furtwangen
* Hochschule Koblenz - Standort Remagen
* HSPV NRW
* in2code
* INOTEC Sicherheitstechnik GmbH
* Institut national d'excellence en santé et en services sociaux inesss.qc.ca
* internezzo ag
* IW Medien GmbH
* jweiland
* Kassenärztliche Vereinigung Rheinland-Pfalz
* Kreis Euskirchen
* L.N. Schaffrath DigitalMedien GmbH
* La Financière agricole du Québec
* Landeskriminalamt Thüringen
* Leuchtfeuer Digital Marketing GmbH
* Lingner Consulting New Media GmbH
* LOUIS INTERNET GmbH
* Marketing Factory
* media::essenz
* medien.de mde GmbH
* mehrwert intermediale kommunikation GmbH
* Meridium Technologies
* MOSAIQ GmbH
* NEW.EGO GmbH
* Overlap GmbH & Co KG
* Patrick Gaumond Québec.ca gouv.qc.ca
* peytz.dk
* pietzpluswild GmbH
* Pixelant / Resultify
* Plan Software GmbH
* Plan.Net France
* ProPotsdam GmbH
* Provitex GmbH
* queo GmbH
* rms. relationship marketing solutions GmbH
* RR Anwendungsentwicklung
* Sandstein Neue Medien GmbH
* Schoene neue kinder GmbH
* seam media group gmbh
* Shop F7 Media GmbH
* SITE'NGO
* Snowflake Productions GmbH
* SOS Software Service GmbH
* Stadtverwaltung Villingen-Schwenningen
* Stämpfli AG
* systime.dk
* Talleux & Zöllner GbR
* tirol.gv.at
* toumoro.com
* Typoheads GmbH
* UEBERBIT GmbH
* unternehmen online GmbH Co. KG (UO)
* Verband der Vereine Creditreform e.V.
* VisionConnect.de
* visol digitale Dienstleistungen GmbH
* visuellverstehen GmbH
* WACON Internet GmbH
* webconsulting business services gmbh
* Webtech AG
* Werbeagentur netzpepper
* werkraum Digitalmanufaktur GmbH
* wow! solution
* zimmer7 GmbH

How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us by becoming an EB partner:

https://shop.dkd.de/Produkte/Apache-Solr-fuer-TYPO3/

or call:

+49 (0)69 - 2475218 0


