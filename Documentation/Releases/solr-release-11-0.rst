.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _releases-11:

============================
Apache Solr for TYPO3 11.0.0
============================

We are happy to release EXT:solr 11.0.0.
The focus of this release was the support of TYPO3 10 LTS.

**Important**: This version is installable with TYPO3 9 and 10 LTS. For TYPO3 9 LTS at least version 9.5.16 is required.
EXT:solr 11 requires the usage of the TYPO3 site handling for the configuration of solr.

The ```legacyMode``` that allows the usage of domain records and configuration of solr cores in TypoScript was dropped with EXT:solr 11.

New in this release
===================

Support of TYPO3 10 LTS
-----------------------

With EXT:solr 11 we provide the support of TYPO3 10 LTS. If you update to EXT:solr 11, make sure, that you are using the TYPO3 site management to manage your Apache Solr endpoints.

Thanks to: Achim Fritz & b13 for the support on that topic

Support of Apache Solr 8.5.1
----------------------------

With EXT:solr 11 we support Apache Solr 8.5.1, the latest release of Apache Solr.

To see what was changed in Apache Solr 8.5.x please read the release notes of Apache Solr:

https://archive.apache.org/dist/lucene/solr/8.5.1/changes/Changes.html

Small improvements and bugfixes
-------------------------------

Beside the major changes we did several small improvements and bugfixes:

* Enable SuggestAction to Provide pure JSON https://github.com/TYPO3-Solr/ext-solr/pull/2544 (Thanks to Julian Strecker)
* Update PHP class docblock https://github.com/TYPO3-Solr/ext-solr/pull/2543 (Thanks to Jens Jacobsen)
* Add typecasting https://github.com/TYPO3-Solr/ext-solr/pull/2487 (Thanks to dev-rke)
* Fix misinterpreted environment variables https://github.com/TYPO3-Solr/ext-solr/pull/2550 (Thanks to in2code and Markus Friedrich)
* IndexInspector is showing wrong core to document relation https://github.com/TYPO3-Solr/ext-solr/issues/2553 (Thanks to Timo Hund)
* Implode deprecation for PHP 7.4  https://github.com/TYPO3-Solr/ext-solr/pull/2558 (Thanks to Goddart Gothe)
* Place autocomplete div inside form https://github.com/TYPO3-Solr/ext-solr/issues/2569 (Thanks to Koen Wouters)
* Docker image managed resources are not writable https://github.com/TYPO3-Solr/ext-solr/pull/2583 (Thanks to Rafael Kähm)
* Fix indexing when default language is disabled in site config https://github.com/TYPO3-Solr/ext-solr/pull/2596 (Thanks to Patrick Daxböck, Hannes Lau, Kai Lochbaum & Timo Hund)
* Use object manager in all places of facet creation https://github.com/TYPO3-Solr/ext-solr/pull/2532 (Thanks to Sascha Nowak)
* Allow Wildcards in Filter Queries https://github.com/TYPO3-Solr/ext-solr/pull/2535 (Thanks to Philipp Parzer)
* Add FAQ for different host/port configuration https://github.com/TYPO3-Solr/ext-solr/pull/2509 (Thanks to Florian Langer)
* Replace current URL with new filter URL https://github.com/TYPO3-Solr/ext-solr/pull/2557 (Thanks to Klaus Hörmann-Engl)
* Add colon after user if password given https://github.com/TYPO3-Solr/ext-solr/pull/2537 (Thanks to Thomas Löffler)
* Remove eval int from port in site configuration  https://github.com/TYPO3-Solr/ext-solr/pull/2599 (Thanks to Georg Ringer)
* Replace usage of TYPO3_branch https://github.com/TYPO3-Solr/ext-solr/pull/2600 (Thanks to Georg Ringer)
* Remove langdisable=1 in FlexForms https://github.com/TYPO3-Solr/ext-solr/pull/2601 (Thanks to Georg Ringer)

Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* dev-rke
* Florian Langer
* Georg Ringer
* Goddart Goth
* Hannes Lau
* Jens Jacobsen
* Kai Lochbaum
* Klaus Hörmann-Engl
* Koen Wouters
* Markus Friedrich
* Markus Schwemer
* Patrick Daxböck
* Philipp Parzer
* Rafael Kähm
* Sascha Nowak
* Thomas Löffler
* Timo Hund

Also a big thanks to our partners that have joined the EB2020 program:

* +Pluswerk AG
* .hausformat GmbH
* 3m5. Media GmbH
* 4eyes GmbH
* Agora Energiewende Smart Energy for Europe Platform (SEFEP) gGmbH
* Amedick & Sommer Neue Medien GmbH
* AUSY SA
* b13 GmbH
* BARDEHLE PAGENBERG Partnerschaft mbB
* BIBUS AG Group
* Bitmotion GmbH
* brandung GmbH & Co. KG
* cab services ag
* clickstorm GmbH
* comwrap GmbH
* cron IT GmbH
* CS2 AG
* cyperfection GmbH
* digit.ly GmbH
* Digitale Offensive GmbH Internetagentur
* E-Magineurs
* Eidg. Forschungsanstalt WSL
* FGTCLB GmbH
* FTI Touristik GmbH
* GAYA - Manufacture digitale
* Hochschule für Polizei und öffentliche Verwaltung Nordrhein-Westfalen
* hotbytes GmbH & Co. KG
* IHK Neubrandenburg
* in2code GmbH
* Inotec Sicherheitstechnik GmbH
* jweiland.net
* Kassenzahnärztliche Vereinigung Bayerns (KZVB)
* Kassenärztliche Vereinigung Rheinland-Pfalz
* Landeskriminalamt Thüringen
* LfdA – Labor für digitale Angelegenheiten GmbH
* Macaw Germany Cologne GmbH
* Marketing Factory Consulting GmbH
* Masterflex SE
* mehrwert intermediale kommunikation GmbH
* mm Online Service
* netlogix GmbH & Co. KG
* Open New Media GmbH
* plan.net - agence conseil en stratégies digitales
* plan2net GmbH
* PROFILE MEDIA GmbH
* ressourcenmangel dresden GmbH
* RKW Rationalisierungs- und Innovationszentrum der Deutschen Wirtschaft e. V.
* ruhmesmeile GmbH
* Sandstein Neue Medien GmbH
* Stadt Wien - Wiener Wohnen Kundenservice GmbH
* Stefan Galinski Internetdienstleistungen
* TOUMORØ
* Typoheads GmbH
* unternehmen online GmbH & Co. KG
* VisionConnect GmbH
* werkraum Digitalmanufaktur GmbH
* WIND Internet
* zimmer7 GmbH


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


