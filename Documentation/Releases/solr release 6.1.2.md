6.1.2 - Maintenance release

# Apache Solr for TYPO3 version 6.1.2 released

This release is a bugfix only release.

## Missing parameter type in FrequentSearches::postInitializeTemplateEngine

This missing parameter type leads to a warning in PHP7.

https://github.com/TYPO3-Solr/ext-solr/pull/1386

## Enable zero-configuration use of Docker image

Creates the datafolder before giving the permissions and allows to use the Dockerfile without further configuration.

https://github.com/TYPO3-Solr/ext-solr/issues/1278

## Allow 'hide default translation of page' on root page

If the root page is set to 'hide default translation of page' this causes a 404 in TypoScriptFrontendController with initializing the TSFE. To avoid this a language uid has to be thrown to the initialize function.

https://github.com/TYPO3-Solr/ext-solr/pull/1414

## Language is lost for link in custom record with sys_language_mode = content_fallback

A cache in getFullItemRecord avoids the correct initialization of the TSFE. Since Util::initializeTsfe is cached anyways, this cache could be removed and makes sure that a proper TSFE is initialized.

# Contributors

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors for this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Hannes Lau
* Markus Friedrich
* Patrick Schriner
* Sebastian Hofer
* Timo Hund

Also a big thanks to our partners that have joined the EB2017 program:

* .hausformat
* AGENTUR FRONTAG AG
* amarantus - media design & conding Mario Drengner & Enrico Nemack GbR
* Amedick & Sommer Neue Medien GmbH
* Andrea Pausch
* Animate Agentur für interaktive Medien GmbH
* artig GmbH & Co. KG
* b:dreizehn GmbH
* BIBUS AG Group
* Bitmotion GmbH
* cab services ag
* Causal Sarl
* Citkomm services GmbH
* clickstorm GmbH
* Creative360
* cron IT GmbH
* CYBERhouse Agentur für interaktive Kommukation GmbH
* data-graphis GmbH
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
* iresults gmbh
* ITK Rheinland
* itl Institut für technische Literatur AG
* jweiland.net 
* Kassenärztliche Vereinigung Rheinland-Pfalz
* Kerstin Nägler Web & Social Media Beratung
* Landesinstitut für Schule und Medien Berlin-Brandenburg
* Leibniz Universität IT Services
* Lime Flavour GbR
* LOUIS INTERNET
* Maximilian Walter
* MEDIA:ESSENZ
* mehrwert intermediäre kommunikation GmbH
* Mercedes-AMG GmbH
* mlm media process management GmbH
* n@work Internet Informationssystems GmbH
* Netcreators
* netz-haut GmbH
* neuwerk interactive
* Nintendo of Europe GmbH
* Onedrop Solutions GmbH
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
* sitegeist media solutions GmbH
* Star Finanz-Software Entwicklung und Vertriebs GmbH
* Stefan Galinski Internetdienstleistungen
* Stratis - Toulon
* Studio 9 GmbH
* Systime A/S
* SYZYGY Deutschland GmbH
* takomat Agentur GbR
* THE BRETTINGHAMS GmbH
* TOUMORO
* Triplesense Reply GmbH
* Typoheads GmbH
* Universität Bremen
* VERDURE Medienteam GmbH
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

## How to Get Involved

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us in 2017 by becoming an EB partner:

http://www.typo3-solr.com/en/contact/ 

or call:

+49 (0)69 - 2475218 0