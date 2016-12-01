# Dia dhuit - Hello "Apache Solr for TYPO3" 6.0

We're happy to announce the release of Apache Solr for TYPO3 (EXT:solr) version 6.0.0. With this release we ship the support for Apache Solr 6.3 and beside that deliver a docker container, that could be used to spin up a configured solr server we a few steps. Beside that we deliver many other features and bugfixes.

## New in this Release

In this release we've merged over 120 pull requests! With these pull requests several new features and bugfixes were added.

### Apache Solr 6.3 support

The focus of this release was the support of the latest Apache Solr version and the simplification of the setup. The configuration of Apache Solr is now done with a configSet. This configSet contains everything you need (Solrconfig, Schema & custom jar files), to run a configured solr server.

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/pull/712
* https://github.com/TYPO3-Solr/ext-solr/pull/711
* https://github.com/TYPO3-Solr/ext-solr/pull/598
* https://github.com/TYPO3-Solr/ext-solr/pull/596
* https://github.com/TYPO3-Solr/ext-solr/pull/591
* https://github.com/TYPO3-Solr/ext-solr/pull/588
* https://github.com/TYPO3-Solr/ext-solr/pull/587
* https://github.com/TYPO3-Solr/ext-solr/pull/580
* https://github.com/TYPO3-Solr/ext-solr/pull/576

### Accessfilter can support multiValue access field

The next release of EXT:solrfal will allow merging of duplicates into one document. The implementation of this feature requires, that the permissions are stored in a multiValue field because the different references to the file can have different permissions. The access field is now a multiValue field and the access filter plugin is able to evaluate multiple access values.

**Related PRs:**

https://github.com/TYPO3-Solr/ext-solr/pull/675

### Fluid usage for system reports

Due to the availability of a fluid standalone view we've adapted the reports to use these views and create a better output.

**Related PRs:**

https://github.com/TYPO3-Solr/ext-solr/pull/740

### Support new languages (Irish, Serbian and Latvian)

With the new Apache Solr version we've added the Language "Irish", "Serbian" and "Latvian", you can now use them to index these languages and use a prepared solr core for them.

**Related PRs:**

https://github.com/TYPO3-Solr/ext-solr/pull/779

### Use extbase command controller to update connections

The old cli commands have been replaced with an extbase command controller. 

Please use now the following commands:

```bash
php ./typo3/cli_dispatch.phpsh extbase solr:updateConnections
```

**Related PRs:**

https://github.com/TYPO3-Solr/ext-solr/pull/694

### Closer to 8 LTS

Our goal is to be ready as soon as possible when 8 LTS will be released. Many patches from the community have been merged to improve the compatibility for version 8. Thanks to all who worked on that!

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/pull/633
* https://github.com/TYPO3-Solr/ext-solr/pull/669

### Performance improvements

Avoid schema retrieval:

By now the solr schema has been fetched in the frontend even when it's not needed. Now we only fetch the schema in the backend, when it's really needed (when stopswords or synonyms are updated).

Reduce ping requests:

The amount of ping requests has been reduced to only do one ping when the plugin is rendered.

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/pull/784
* https://github.com/TYPO3-Solr/ext-solr/pull/776

### Allow solr side facet limit

By now you could only limit the facets that are shown in the frontend. Sometimes you want to reduce the facets earlier, when you retrieve them from solr.

The following setting allows you now to configure this limit:

```bash
plugin.tx_solr.search.faceting.facetLimit = 50
```

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/issues/724

### Improved code quality with scrutinizer

To ensure changes don't reduce the quality of the code, we've introduced scrutinizer-ci to give us a feedback on the code. After adding scrutinizer-ci we also started to resolve these issues step by step and to increase the coverage of automated tests.

You can have a look on the results of the inspections and see the impact there:

https://scrutinizer-ci.com/g/TYPO3-Solr/ext-solr/statistics/

A few pull requests have been merged to introduce scrutinizer and to fix several issues:

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/pull/679
* https://github.com/TYPO3-Solr/ext-solr/pull/706
* https://github.com/TYPO3-Solr/ext-solr/pull/744

Our goal is here to improve the code step by step and don't get worse.

### Use compound index format

To avoid an error in Apache Solr with "too many open files", it is possible to use an compound index format. Apache Solr is then writting into one index file instead of many. The drawback is that the performance will be a little less, but not significant for the most projects. We decided to change the default configuration here to a more robust setting. When you have very high performance requirements, you are still able to changes this by setting "<useCompoundFile>false</useCompoundFile>" in your solrconfig.xml.

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/issues/695

## Breaking changes

From version 5.1.1 there are some breaking changes that your need to keep in mind, when you update.

###Apache Solr 6 and access filter

Since Apache Solr 6 is containing an embedded jetty server and the setup is totally different to the setup of Apache Solr 4.10 it is required, to install a new Apache Solr Server. This can be done with our install script for development, or you can use our docker image to start a new solr server as a container. Both approaches are documented in our documentation.

###Migration to command controllers

We are now using a command controller to update the connections to solr. Please use the following command now, to update your connections:

```bash
php ./typo3/cli_dispatch.phpsh extbase solr:updateConnections
```

##Bugfixes

The following bugs have been fixed in this release.

* Page is not added to queue when page_language_overlay record changed 
https://github.com/TYPO3-Solr/ext-solr/pull/768
* Use TypoScript configuration object for plugin baseWrap
https://github.com/TYPO3-Solr/ext-solr/pull/697
* Fixed group by and statement in statistics
https://github.com/TYPO3-Solr/ext-solr/pull/722
* Make definition of cookie in tx_solr_statistics compatible with ses_id in fe_sessions
https://github.com/TYPO3-Solr/ext-solr/pull/713
* Fix recursive value resolution in SOLR_RELATION
https://github.com/TYPO3-Solr/ext-solr/pull/692
* Use styled fields for the scheduler tasks
https://github.com/TYPO3-Solr/ext-solr/pull/672
* Have index updated when using frontend editing
https://github.com/TYPO3-Solr/ext-solr/pull/648
* Make suggest working when variants are used
https://github.com/TYPO3-Solr/ext-solr/pull/627

## Outlook

In the next release (6.1) our focus will be to prepare the support of 8 LTS as good as possible. The next release (6.1) will also be the last release for 7.6 LTS.

### Contributors

Like always this release would not have been possible without the help from our
awesome community. These are the contributors for this release.

(patches, comments, bug reports, review, ... in alphabetical order)

* Anjey
* Claus Due
* Daniel Siepmann
* Dominique Kreemers
* Georg Ringer
* Ingo Renner
* Josef Glatz
* Markus Friedrich
* Markus Kasten
* Michiel Roos
* Olivier Dobberkau
* Patrick Oberdorf
* Peter Kraume
* Pierrick Caillon
* Sascha Egerer
* Thomas Hohn
* Timo Hund
* Tomas Norre Mikkelsen

Also a big thanks to our partners that have joined the EB2016 program:

* Arrabiata Solutions GmbH & Co. KG
* avonis
* Bank CIC AG
* Bitmotion GmbH
* Citkomm services GmbH
* cron IT
* CS2 AG
* Cosmoblonde GmbH
* Daniz online markting
* datenwerk innovationsagentur gmbh
* Die Medialen GmbH
* die_schnittsteller GmbH
* E-magineurs
* Fernando Hernáez Lopez
* Future Connection AG
* Gernot Leitgab
* .hausformat
* Hirsch & Wölfl GmbH
* hs-digital GmbH
* IHK Neubrandenburg
* internezzo AG
* jweiland.net
* L.N. Schaffrath DigitalMedien GmbH
* mehrwert intermediale kommunikation GmbH
* netlogix GmbH & Co. KG
* Pixel Ink
* Pixelpark AG
* pixolith GmbH & Co. KG
* polargold GmbH
* portrino GmbH
* Q3i GmbH & Co. KG
* raphael gmbh
* RUAG Corporate Services AG
* sitegeist media solutions GmbH
* ST3 Elkartea
* Star Finanz-Software Entwicklung und Vertriebs GmbH
* Stefan Galinski Interndienstleistungen
* Speedpartner GmbH
* sunzinet AG
* Systime A/S
* SYZYGY Deutschland GmbH
* tecsis GmbH
* web-vision GmbH
* websedit AG - Internetagentur
* Webstobe GmbH
* werkraum GmbH
* WIND Internet
* wow! solution
* zdreicon AG

Thanks also to our partners that singed up a partnership for 2017 (EB2017) allready:

* cron IT GmbH
* b:dreizehn GmbH
* Die Medialen GmbH
* LOUIS INTERNET
* polargold GmbH
* Mercedes-AMG GmbH
* Triplesense Reply GmbH
* zdreicom AG

Thanks to everyone who helped in creating this release!

## How to get involved

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports, and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help answering questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us in 2017 by getting an EB partner:

http://www.typo3-solr.com/en/contact/ 

or call:

+49 (0)69 - 2475218 0


