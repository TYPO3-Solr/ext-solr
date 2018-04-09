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

===========================
8.0.3 - Maintenance release
===========================

This release is a bugfix only release. It ships an security update of Apache Solr from 6.6.2 to 6.6.3.

Update to Apache Solr 6.6.3
===========================

There was a security issue with Apache Solr 6.6.2 in combination with the DataImportHandler. This handler is disabled by default in our configuration and you only need to update when you have enabled the DataImportHandler.

Add additional class for focus on search query input
====================================================

Add's an additional class tx-solr-suggest-focus to the input field and uses it in the suggest javascript controller.

Contributors
============

Thanks to all contributors:

* Marc Bastian Heinrichs
* Timo Hund

Big thanks to our partners that have joined the EB2018 program:

* 4eyes GmbH
* Albervanderveen
* Agentur Frontal AG
* Amedick & Sommer
* AUSY SA
* Bibus AG
* Bitmotion GmbH
* bgm Websolutions GmbH
* Citkomm services GmbH
* Consulting Piezunka und Schamoni - Information Technologies GmbH
* Cows Online GmbH
* food media Frank Wörner
* Fachhochschule für öffentliche Verwaltung NRW
* FTI Touristik GmbH
* Hirsch & Wölfl GmbH
* Hochschule Furtwangen
* Image Transfer GmbH
* JUNGMUT Communications GmbH
* Kreis Coesfeld
* LOUIS INTERNET GmbH
* L.N. Schaffrath DigitalMedien GmbH
* MEDIA::ESSENZ
* Mercedes AMG GmbH
* Petz & Co
* pietzpluswild GmbH
* plan.net
* Pluswerk AG
* PROFILE MEDIA GmbG
* Q3i GmbH & Co. KG
* ressourcenmangel an der panke GmbH
* Roza Sancken
* Site'nGo
* Studio B12 GmbH
* systime
* Talleux & Zöllner GbR
* TOUMORO
* TWT Interactive GmbH
* T-Systems Multimedia Solutions GmbH
* Ueberbit GmbH

Special thanks to our premium EB 2018 partners:

* b13 http://www.b13.de/
* dkd http://www.dkd.de/
* jweiland.net http://www.jweiland.net/
* sitegeist http://www.sitegeist.de/


Thanks to everyone who helped in creating this release!

How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us in 2018 by becoming an EB partner:

http://www.typo3-solr.com/en/contact/

or call:

+49 (0)69 - 2475218 0