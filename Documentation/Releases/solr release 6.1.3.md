6.1.3 - Maintenance release

# Apache Solr for TYPO3 version 6.1.3 released

This release is a bugfix only release.

## Optimize composer.json

This change:

* Removes "minimum-stability": "alpha" from the composer.json
* Removes "optimize-autoloader": true from the composer.json
* Adds "extension-key": "solr" to the composer json.

https://github.com/TYPO3-Solr/ext-solr/pull/1527

## Escape output of ScoreCalculation

Applies htmlspecialchars on the output of the ScoreCalculation.
https://github.com/TYPO3-Solr/ext-solr/pull/1528

## Escape output of SiteSelector

Applies htmlspecialchars on the output of the SiteSelector.

https://github.com/TYPO3-Solr/ext-solr/pull/1533

## Relation handler should handle pages overlays correctly

The relation handler did not handle page overlays correctly before. 
This pr provides a fix to handle the translation of pages correctly.

https://github.com/TYPO3-Solr/ext-solr/pull/1535

# Contributors

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors for this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Frans Saris
* Georg Ringer
* Rafael Kähm
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