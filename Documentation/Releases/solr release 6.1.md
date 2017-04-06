# We are ready for TYPO3 8 LTS - 6.1 has been released

Close to the release of TYPO3 8 LTS we are happy to announce EXT:solr 6.1 that is compatible with TYPO3 8 LTS.

## New in this Release

In this release we've merged over 160 pull requests! With these pull requests several new features and bugfixes were added.

### Compatible with TYPO3 8 LTS and PHP 7.1 ready

EXT:solr 6.1 is ready for TYPO3 8 LTS and php 7.1 while keeping the compatibility to TYPO3 7.6 LTS and PHP 5.5 and 5.6.

### Backend Performance Improvements

During the development of version 6.1 there have been a lot of smaller and bigger performance improvements.
The following changes might be interesting.

#### Allow to use the closest configuration in the page tree

When a record is saved in the backend by now the whole typoscript configuration is evaluated for the page where the record is located. In many setups it is enough to just use the closest template in the rootline to parse the configuration.
Since there are cases, where this method does not work (e.g. when you use conditions based on page ids) you need to switch this behaviour explicitly on, by enable "useConfigurationFromClosestTemplate".

* https://github.com/TYPO3-Solr/ext-solr/issues/937

#### Add caching when record monitor is used

To optimize the performance the RecordMonitor and GarbageCollector class have been splitted into several components. These components use the TYPO3 caching framework to cache result that do not need to be retrieved multiple times.

* https://github.com/TYPO3-Solr/ext-solr/issues/940
* https://github.com/TYPO3-Solr/ext-solr/issues/970

#### Add a global list of monitored tables

If a record is relevant can only be decided in the context of a solr site because the typoscript configuration defines the indexing configuration.

When you want to tweek the backend performance you can define a global list of monitoredTables. Other tables will then be ignored and also the parsing of the typoscript configuration is not needed then.

* https://github.com/TYPO3-Solr/ext-solr/issues/1115

### New Index Inspector

Since extjs will be removed more and more from the TYPO3 core we decided to migrate the index inspector to fluid.
You can use the index inspector as usual from "Web > Info > Search Index Inspector" to analyze which documents are in the 
solr server for a specific page.

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/948
* https://github.com/TYPO3-Solr/ext-solr/issues/34

### Allow to monitor records outside the siteroot

When the property "additionalPageIds" is configured for an indexing configuration this configuration is now taken into account in the record monitor to re-queue these elements when a change in the backend is done.

Since this is an expensive operation, you can disable this feature by configuring the extension setting (useConfigurationTrackRecordsOutsideSiteroot) in the extension manager.

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/485

### Use TYPO3 logging framework instead of devlog

By now the devLog from TYPO3 was used to log messages in the extension. Since several years there is a more flexible logging framework available in the TYPO3 core.
In EXT:solr 6.1 we make use of this and use the TYPO3 logging framework to write the logs.

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/490

### Use ::class and shorten array syntax

The minimum php version (PHP 5.5) allows us to benefit from PHP language features that make the code more readable:

* Short array syntax: We replaced the parts in the code where `[]` can be used instead of `array()`
* ::class: By using `::class` all places where classes get instanciated with GeneralUtility::makeInstance can be simplyfied


Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/476
* https://github.com/TYPO3-Solr/ext-solr/issues/477

### Support partial matches with N-Gram and Edge N-Gram

For some search use cases it makes sence to support partial matches e.g. when you search for "boch" and want to mach a field with the content "bochum".

EXT:solr 6.1.0 shippes new dynamic field types that make "N-Gram" and "Edge N-Gram" processed fields available:

* Edge N-Gram Singlevalue: `\*_textEdgeNgramS`  Edge Ngram (hello => hello, hell..)
* Edge N-Gram Multivalue: `\*_textEdgeNgramM`  Edge Ngram (hello => hello, hell..)
* N-Gram Singlevalue: `\*_textNgramS` Ngram (hello => he,ll,lo,hel,llo)
* N-Gram Multivalue: `\*_textNgramM` Ngram (hello => he,ll,lo,hel,llo) 
    
See also:
    
https://cuiborails.wordpress.com/2012/07/13/apache-solr-ngramedgengram/

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/906


### Allow to disable siteHash check by using allowedSites = *

The siteHash is unique for a solr site in the TYPO3 system. When solr does a query the allowedSites setting can be used to control the set of documents that are queried in a solr core.
This is useful when you want to split the content from multiple sites in a single solr core. In some cases it is useful to disable this limitation. 

E.g. when:

* You have data in solr that comes from another system
* When you want to search across multiple sites

Before the extension "solr_disablesitehash" was required to turn this sitehash check off. With solr 6.1.0 we've changed the meaning of the allowedSites:

* Before: \* was the same as __all, which means all sites in the system
* After: __all is still handled as __all sites in the system, but * now means every site (same as no check at all)
    
Migration: When you are using * for query.allowedSites change the setting to __all.

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/862

### Update jQuery version

In this release we have updated the shipped jQuery and jQueryUi components to the following versions:

* jQuery: v3.1.1
* jQuery UI: v1.12.1

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/518

### Fix scrutinizer issues

A lot of issues (formatting, small bugs, documentation,...) reported by scrutinizer have been resolved in this release.

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/1100
* https://github.com/TYPO3-Solr/ext-solr/issues/1079
* https://github.com/TYPO3-Solr/ext-solr/issues/1070
* https://github.com/TYPO3-Solr/ext-solr/issues/1066
* https://github.com/TYPO3-Solr/ext-solr/issues/1064
* https://github.com/TYPO3-Solr/ext-solr/issues/1010


### Add solr access filter plugin 2.0.0

The solr access filter plugin has been optimized to use a solr post filter. By using a post filter the performance of this plugin is much better, because less documents need to be evaluated.
In this release we ship this new version 2.0.0 of the access filter with the default configSet and in our docker container.

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/933

### Preparation for Doctrine migration

In the next release we will drop the support of TYPO3 7 LTS and only support 8 LTS. Since in the TYPO3 core the database access was migrated to Doctrine, we will use Doctrine as well.

To simplify this, we have centralized a lot of database queries in this release and also dropped duplicate queries to reduce to amount of work that needs to be done for this in the next releaese.

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/908
* https://github.com/TYPO3-Solr/ext-solr/issues/1128
* https://github.com/TYPO3-Solr/ext-solr/issues/1129

### Allow self signed ssl certificates

Not every project can use officially signed SSL certificates for all stages. Because of that we allowed for the page indexing to index a host, with a self signed certificate.

You can enable this feature by configuring the extension setting (allowSelfSignedCertificates) in the extension manager to true.

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/1134
* https://github.com/TYPO3-Solr/ext-solr/issues/1173

### Add cObject support for solr settings

When you use EXT:solr in a deployment scenario (e.g. platform.sh) you maybe want to define the solr endpoints by environment variables or from variables in TYPO3_CONF_VARS. Both approaches are supported by the typoscript TEXT object.
Therefore it makes sence for the solr endpoint settings in `plugin.tx_solr.solr` to support the usage of cObjects there. This allows you to define connections like this:

Addition to AdditionalConfiguration.php:
    
```
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['host'] = 'mysolrserver.de';
```

Usage in Typoscript:
    
```
plugin.tx_solr.solr {
    host = TEXT
    host {
        value = localhost
        override.data = global:TYPO3_CONF_VARS|EXTCONF|solr|host
    }
}
```


Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/868

## Deprecated code

For the continues improvement of the codestructure and preparation for upcoming tasks, the following methods have been marked as deprecated and will be removed in EXT:solr 7.0 (next release):

* GarbageCollector::cleanIndex
* Query::addSortField
* Query::getSortingFields
* SolrService::getSchemaName
* Util::timestampToIso
* Util::isoToTimestamp
* Util::timestampToUtcIso
* Util::utcIsoToTimestamp
* Util::getRootPageId
* Util::isRootPage
* Util::getSiteHashForDomain
* Util::resolveSiteHashAllowedSites
* Item::updateIndexedTime
* Queue::getIndexingConfigurationByItem
* Queue::getIndexingConfigurationsByItem
* Queue::getItemsCountBySite
* Queue::getRemainingItemsCountBySite
* TypoScriptConfiguration::getIndexQueuePagesAllowedPageTypesArray
* QueryTest::canAddSortField
* Site::getLanguages
* Site::getFirstAvailableSite
* Site::getAvailableSites
* Site::getAvailableSitesSelector

Please check your deprecation log and replace the usages in your code.

## Breaking changes

In this release some breaking changes have been required. Please follow the notes below when you migrate from 6.0 to 6.1

### Default variantId changed

The default variantId was `table/uid` before since this id is not unique across multiple TYPO3 system, a system hash was added before.
The scheme of the new variantId is `systemhash/table/uid` and allows to use grouping when data from multiple TYPO3 systems get indexed and searched. 

Migration:

Reindex the solr data to get the new variantId.

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/862

### Semantic of allowedSites changed

Migration:

When you use `plugin.tx_solr.search.query.allowedSites = *` you should now use `plugin.tx_solr.search.query.allowedSites = __all`

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/862

### Setting debugDevlogOutput replaced by debugOutput

Because the devLog was replaced by the logging framework, the setting `plugin.tx_solr.logging.debugDevlogOutput` does not make sence anymore. 

Migration:

When you want to see the log output in the backend or frontend please use `plugin.tx_solr.logging.debugOutput` now.

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/490

### "Only variables should be assigned by reference" in IndexQueue\Indexer::preAddModifyDocuments()

Along we the removal of the reference ``GeneralUtility::getUserObj`` was replaced  with ``GeneralUtility::makeInstance``, because the usage with ":" is deprecated since TYPO3 8 and will be removed.

Migration:

When you reference custom indexer (in ``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['IndexQueueIndexer']['indexItemAddDocuments']``) you should reference only an autoloadable classname.

The following steps are required:

* Move your Indexer to an own extension with proper autoloading configuration
* Reference the autoloadable classname

Related Issues:

* https://github.com/TYPO3-Solr/ext-solr/issues/1183


## Bugfixes

StatisticRepository::getTopKeyWordsWithOrWithoutHits $limit, $withoutHits have no default values 

https://github.com/TYPO3-Solr/ext-solr/issues/1143

Wrong TS path in frontendDataHelper documentation 

https://github.com/TYPO3-Solr/ext-solr/issues/964

Invalid include paths after scheduler run

https://github.com/TYPO3-Solr/ext-solr/issues/921

Backend Summary not working

https://github.com/TYPO3-Solr/ext-solr/issues/731

Queue initialization returns wrong (duplicate) results for second site root in multisite environment 

https://github.com/TYPO3-Solr/ext-solr/issues/488

solrconfig.xml More Like This Handler configuration is broken

https://github.com/TYPO3-Solr/ext-solr/issues/765

Fix position of default stopwords

https://github.com/TYPO3-Solr/ext-solr/issues/578

Bug/unwanted feature in method getPages in Site.php

https://github.com/TYPO3-Solr/ext-solr/issues/652

Custom field is not indexed for custom page queue configuration

https://github.com/TYPO3-Solr/ext-solr/issues/842
 
GarbageCollector fails to check endtime correctly

https://github.com/TYPO3-Solr/ext-solr/issues/1212

"Only variables should be assigned by reference" in IndexQueue\Indexer::preAddModifyDocuments()

https://github.com/TYPO3-Solr/ext-solr/issues/1183

Use urlencode for deletion of synonyms and stopwords

https://github.com/TYPO3-Solr/ext-solr/issues/1205
https://github.com/TYPO3-Solr/ext-solr/issues/1206

## Outlook

This is the last release for TYPO3 7.6 LTS and the last release with marker based templating. The next release (7.0.0) will drop the marked based templating and will require TYPO3 8 LTS. If you want to start with fluid templating now we suggest to use solrfluid (available as EB partner) 

## Thanks

Thanks to everyone who has supported the release of EXT:solr 6.1.0.

### Contributors

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors for this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Andreas Allacher
* Andriy Oprysko
* Arno Schoon
* Frank Nägler
* Frans Saris
* Ingo Renner
* Josef Glatz
* Markus Kobligk
* Rafael Kähm
* Rasmus Larsen
* Sascha Egerer 
* Thomas Hohn
* Timo Hund
* Tomas Norre Mikkelsen 

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


