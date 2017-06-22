6.1.1 - Maintenance release

# Apache Solr for TYPO3 version 6.1.1 released


This release is a bugfix only release.

## Ignore workspaces in RootPageResolver

When the rootPage resolver was called with $pageId = -1 it tried to check if this is really a rootPage.
Since we know that this is a workspace page, the RootPageResolver returns false now.

* https://github.com/TYPO3-Solr/ext-solr/issues/1277


## Allow to limit the solr:updateConnections command to a rootpageid

To be able to initialize only a few connections the option --rootpageid was added.

* https://github.com/TYPO3-Solr/ext-solr/issues/1288

## Indexing records outside the rootline throws Exception

The Indexer uses the item record page to resolve the configuration. This can lead to problems when the records is outside a siteroot because the configuration is not present there.

This fix uses the configuration of the siteroot when no configuration is present as fallback to have an indexing configuration available.

* https://github.com/TYPO3-Solr/ext-solr/issues/1291

## Tracking changes in records of other siteroots is not working as expected

The following scenario is given:

* Site a
* Site b

Site a references record from site b in additionalPageIds. When a change is done in the record we expect that the index queue item for both sites is created, since it is referenced in both sides. By now the tracking of the record outside the siteroot is only working when the referenced record does not belong to another site.

* https://github.com/TYPO3-Solr/ext-solr/issues/1347

# Contributors

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors for this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Andreas Lappe
* Markus Friedrich
* Rafael Kähm
* Rémy Daniel
* Timo Hund

Also a big thanks to our partners that have joined the EB2017 program:

* Amedick & Sommer
* amarantus - media design & coding Mario Drengner & Enrico Nemack GbR
* Animate Agentur für interaktive Medien GmbH
* artif GmbH & Co. KG
* AVM Computersysteme Vertriebs GmbH
* b:dreizehn GmbH
* Bitmotion GmbH
* cab services ag
* Causal Sàrl
* Creative360
* cron IT GmbH
* data-graphics GmbH
* Deutscher Ärzteverlag GmbH
* Deutscher Volkshochschul-Verband
* Die Medialen GmbH
* dörler engineering services
* hauptsache.net
* Havas Düsseldorf GmbH
* itl AG
* jweiland.net
* KEMWEB GmbH & Co. KG
* Leibniz Universität IT Services, Hannover
* Lime Flavour GbR
* LOUIS INTERNET
* Maximillian Walter
* Mercedes-AMG GmbH
* mpm media process management GmbH
* n@work Internet Informationssysteme GmbH
* Netcreators BV (Netherlands)
* NetSpring s.r.l.
* netz-haut GmbH
* polar gold
* punkt.de
* sitegeist media solutions GmbH
* Star Finanz GmbH
* Studio 9 GmbH
* stratis
* systime
* takomat Agentur GbR
* Triplesense Reply
* Typoheads GmbH
* UEBERBIT GmbH
* WACON Internet GmbH
* Universität Bremen
* webconsulting business services gmbh
* zdreicom AG
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