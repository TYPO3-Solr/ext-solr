# Dia dhuit - Hello "Apache Solr for TYPO3" 6.0

We're happy to announce the release of Apache Solr for TYPO3 (EXT:solr) version 6.0.0. 
With this release we ship support for Apache Solr 6.3 and provide a Docker file, that 
can be used to spin up a Solr server configured and ready to run within a few steps. 
Besides that the release contains many other features and bugfixes.

## New in this Release

In this release we've merged over 120 pull requests! 
With these pull requests several new features and bugfixes were added.

### Apache Solr 6.3 Support

The focus of this release was adding support for the latest version of Apache Solr 
and making setup easier. Configuration of Apache Solr is now provided via configSets. 
A configSet contains everything you need - solrconfig.xml, schema.xml & custom 
access filter jar files - to configure and run a Solr server for use with TYPO3.

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

### Access Filter Support for multi-value Access Fields

The next release of EXT:solrfal will allow merging of duplicates into one document. 
The implementation of this feature requires that the permissions are stored in a multi-value 
field because different references to a file can have different permissions. 
Thus the access field is now a multi-value field and the access filter plugin 
is able to evaluate multiple access values.

**Related PRs:**

https://github.com/TYPO3-Solr/ext-solr/pull/675

### Fluid Templates for System Status Reports

By using the Fluid standalone views we have adapted the status reports 
to use these views and create better output.

**Related PRs:**

https://github.com/TYPO3-Solr/ext-solr/pull/740

### Support for New Languages (Irish, Serbian and Latvian)

With the new Apache Solr version we have added languages Irish, Serbian, and Latvian. 
You can now use them to index sites in these languages by creating a Solr core using the 
configuration provided by the extension.

**Related PRs:**

https://github.com/TYPO3-Solr/ext-solr/pull/779

### Use Extbase Command Controller to Update Connections

The old cli commands have been replaced with an Extbase command controller. 

Please use the following commands to update Solr connections through cli:

```bash
php ./typo3/cli_dispatch.phpsh extbase solr:updateConnections
```

**Related PRs:**

https://github.com/TYPO3-Solr/ext-solr/pull/694

### Closer to 8 LTS

Our goal is to be able to support TYPO3 CMS v8 as soon as version 8 LTS will be released. 
Many patches from the community have been merged to improve the compatibility for version 8 already. 
Thanks to everyone who worked on that and provided contributions!

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/pull/633
* https://github.com/TYPO3-Solr/ext-solr/pull/669

### Performance Improvements

Avoid schema retrieval:

Until now the Solr schema was fetched in the frontend even when it was not needed. 
Now we only fetch the schema in the backend when it's really needed (when updating stopswords or synonyms).

Reduce ping requests:  

The amount of ping requests has been reduced to only do one ping when the plugin is rendered.

Improved configuration caching:

Configuration object, are now cached in an in memory cache. This gives an improvement when pages in the backend are copied.

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/pull/784
* https://github.com/TYPO3-Solr/ext-solr/pull/776
* https://github.com/TYPO3-Solr/ext-solr/pull/816

### Allow Solr Server-Side Facet Option Limits

Until now you could only limit the facet options shown when they get rendered in the frontend. 
Sometimes however you want to reduce the available facet options at an earlier time when 
retrieving them from Solr.

The following setting now allows you to configure this limit:

```bash
plugin.tx_solr.search.faceting.facetLimit = 50
```

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/issues/724

### Improved Code Quality With Scrutinizer

To ensure changes don't reduce the quality of the code we have introduced Scrutinizer-CI to 
provide feedback on code quality. After adding Scrutinizer-CI we also started to resolve 
initial issues reported step by step and to increase the coverage of automated tests.

You can have a look at the results of the inspections and see the impact there:

https://scrutinizer-ci.com/g/TYPO3-Solr/ext-solr/statistics/

A couple pull requests have been merged to introduce Scrutinizer and to fix several issues:

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/pull/679
* https://github.com/TYPO3-Solr/ext-solr/pull/706
* https://github.com/TYPO3-Solr/ext-solr/pull/744

Our goal is to improve the code step by step and don't get worse.

### Use of Compound Index Format

To avoid an error in Apache Solr with "too many open files", it is possible 
to use the compound index format. Apache Solr is then writting to only one 
index file instead of many. The downside however, is that the performance may 
be slightly reduced but should not be significant for most projects. 
We decided to change the default configuration here to use a more robust setting. 
If you have very high performance requirements you can still change this behavior 
by setting `<useCompoundFile>false</useCompoundFile>` in your solrconfig.xml.

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/issues/695


### Added Schema Field for Exact Matches

We've added a new data type "textExact" to the solr schema. Beside that copyFields have been added for the following fields:

* titleExact
* contentExact
* tagsH1Exact
* tagsH2H3Exact
* tagsH4H5H6Exact
* tagsInlineExact

Beside that this type is also available as dynamic fields with the following suffixes:

* _textExactS
* _textExactM

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/pull/820

### Username and Password for Solr Connection

Username and password can now be configured for the solr connection:

```
plugin.tx_solr.solr.username = username
plugin.tx_solr.solr.password = password
```

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/pull/789

### Timeout for Solr Connection

A timeout for the solr connection can now be configured:

```
plugin.tx_solr.solr.timeout = 20
```

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/pull/798

### Rendering Instruction for DateFormatting

The following rendering instruction can be used, when you want to format a date as option facet and store it as date or timestamp.

```
plugin.tx_solr.search.faceting.facets.created {
   field = created
   label = Created
   sortBy = alpha
   reverseOrder = 1
   renderingInstruction = TEXT
   renderingInstruction {
      field = optionValue
      postUserFunc = ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RenderingInstructions\FormatDate->format
   }
}
```

**Related PRs:**

* https://github.com/TYPO3-Solr/ext-solr/pull/829

## Breaking Changes

Updating from version 5.1.1 or earlier there are some breaking changes that 
your need to keep in mind when updating.

### Apache Solr 6 and Access Filter

Since version 6 Apache Solr comes with an embedded Jetty server which completely 
changes the setup compared to Apache Solr 4.10. It is now required to install a 
new instance of Apache Solr Server. This can be done with our install script for 
development or you can use our Docker image to start a new Solr server as a container. 
Both approaches are described in our documentation.

### Migration to Command Controllers for CLI

We are now using a command controller to update the Solr server connections. 
Please use the following command to update your connections:

```bash
php ./typo3/cli_dispatch.phpsh extbase solr:updateConnections
```

## Bugfixes

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

In the next release (6.1) our focus will be to prepare support for TYPO3 CMS 
version 8 LTS as well as possible. The next release (6.1) will also be the 
last release for TYPO3 CMS version 7.6 LTS.

### Contributors

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors for this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Anjey
* Claus Due
* Daniel Siepmann
* Dominique Kreemers
* Georg Ringer
* Hendrik Putzek
* Ingo Renner
* Josef Glatz
* Markus Friedrich
* Markus Kasten
* Michiel Roos
* Olivier Dobberkau
* Patrick Oberdorf
* Peter Kraume
* Philipp Gampe
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

Thanks also to our partners who already singed up for a 2017 partnership (EB2017):

* Amedick & Sommer Neue Medien GmbH
* cron IT GmbH
* b:dreizehn GmbH
* Die Medialen GmbH
* Leibniz Universität IT Services, Hannover
* LOUIS INTERNET
* polargold GmbH
* Mercedes-AMG GmbH
* Triplesense Reply GmbH
* zdreicom AG

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


