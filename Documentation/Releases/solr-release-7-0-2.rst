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

===================================
Apache Solr for TYPO3 version 7.0.2
===================================

This release is a bugfix only release.

Fix sorting counts in statistics module
=======================================

The sorting counts have been caluclated wrong. This patch fixes the calculation.

See also:

* https://github.com/TYPO3-Solr/ext-solr/pull/1585


Make plugin.tx_solr_PiSearch_Search available again
===================================================

The TypoScript pathes to add the plugin just with TypoScript have been removed. This patch adds these pathes again.

See also:

* https://github.com/TYPO3-Solr/ext-solr/pull/1643

Add default typoscript view configuration
=========================================

The default fallback path was missing in the typoscript (plugin.tx_solr.view.templateRootPaths/partialRootPaths/layoutRootPaths).0

These pathes have been added to the default configuration.

See also:

* https://github.com/TYPO3-Solr/ext-solr/pull/1642

Wrong parameter value for grouping fields
=========================================

The parameter "group.field" was filled with the wrong data. This was fixed.


See also:

* https://github.com/TYPO3-Solr/ext-solr/pull/1650


Enable escaping in TranslateViewHelper and AbstractWidgetViewHelper
===================================================================

The output of the TranslateViewHelper and AbstractWidgetViewHelper was not escaped. If you use user input (e.g. the querystring) in this context, the output was not properly escaped (not the case in the default partials).

Impact:

* When you use the default templates and partials nothing needs to be changed
* When you use a custom partial for the pagination, make sure that no html is passed to the translate ViewHelpers

Check the partial "Resources/Private/Templates/ViewHelpers/Widget/ResultPaginate/Index.html"

Avoid to pass special characters to the translate view helper, e.g. as before:

.. code-block:: typoscript

    <s:translate key="paginate_previous">&laquo;</s:translate>


.. code-block:: typoscript

    <f:if condition="{s:translate(key: 'paginate_next')}">
        <f:then><s:translate key="paginate_next" /></f:then>
        <f:else>&raquo;</f:else>
    </f:if>


See also:

* https://github.com/TYPO3-Solr/ext-solr/pull/1653


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors for this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Jeffrey Nellissen
* Jens Jacobsen
* Rafael Kähm
* Timo Hund
* Thomas Löffler

Also a big thanks to our partners that have joined the EB2017 program:

* .hausformat
* AGENTUR FRONTAG AG
* Agentur rootfeld
* amarantus - media design & conding Mario Drengner & Enrico Nemack GbR
* Amedick & Sommer Neue Medien GmbH
* Andrea Pausch
* Animate Agentur für interaktive Medien GmbH
* Arrabiata Solutions GmbH
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
* Creative360
* cron IT GmbH
* CYBERhouse Agentur für interaktive Kommukation GmbH
* cyperfection GmbH
* data-graphis GmbH
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