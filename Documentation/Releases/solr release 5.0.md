# Apache Solr for TYPO3 version 5.0 released

We're happy to announce the release of Apache Solr for TYPO3 (EXT:solr) version 5.0.0. With this release we ship the requirements to use our new addon EXT:solrfluid, that allows you to render your search results with the fluid templating engine.

Beside the adaptions for solrfluid we mainly did bugfixes and cleanup in the code base. If you want to use solrfluid in your projects, you should signup a partnership with dkd (visit typo3-solr.com or call dkd +49 (0)69 - 247 52 18-0 for more information).

## New in this Release

### Preparing fluid support

* Adding a SearchRequest object

* Improving the SearchResultSetService

### Dropping deprecated code

As announced in Version 4.0 the following methods have been removed:

* Util::getTypoScriptObject please use TypoScriptConfiguration::getObjectByPath() instead.
* Util::isValidTypoScriptPath please use TypoScriptConfiguration::isValidPath() instead.
* Util::getTypoScriptValue please use TypoScriptConfiguration::getValueByPath() instead.
* IndexQueue\Queue::getTableToIndexByIndexingConfigurationName please use TypoScriptConfiguration::getIndexQueueTableNameOrFallbackToConfigurationName instead.
* IndexQueue\Queue::getTableIndexingConfigurations please use TypoScriptConfiguration::getEnabledIndexQueueConfigurationNames instead.
* Plugin\PluginBase::search / PluginBase::getSearch / PluginBase::setSearch please use $pi->getSearchResultSetService()->getSearch() instead.
* Plugin\Results\Results::getAdditionalFilters please use $pi->getSearchResultSetService()->getAdditionalFilters() instead.
* Plugin\Results\Results::getNumberOfResultsPerPage use $pi->getSearchResultSetService()->getNumberOfResultsPerPage() instead.
* Plugin\Results\Results::getAdditionalFilters please use $pi->getSearchResultSetService()->getAdditionalFilters() instead.
* TypoScriptConfiguration::offsetGet / offsetExists / offsetSet please use TypoScriptConfiguration::getObjectByPath / isValidPath / getValueByPath instead. These functions have only been implemented for backwards compatibility in will be removed in 5.0

### Dropping the class maps

Since EXT:solr 5.0 is only running on TYPO3 7.6 LTS and classmap files for backwards compatibility have been dropped. This also gives am small performance improvement for the current version.

### Unify xliff files in one file per language

Because we want to use the labels in EXT:solr and EXT:solrfluid, and extbase requires to use one xliff file per language, we take the chance to streamline the language files into one file per language.

### Performance improvement for TypoScriptConfiguration

Because TypoScriptConfiguration is used quite frequently, we did some performance improvements by optimizing the access to the underling array structure.

### Add additionalWhereClause to SOLR_RELATION

You can now add an additionalWhereClause for SOLR_RELATION items.

Example:

    plugin.tx_solr.index.queue {
        record = 1
        record {
            table = tx_extension_domain_model_record

            fields {
                title = title
                category_stringM = SOLR_RELATION
                category_stringM {
                    localField = category
                    multiValue = 1
                    additionalWhereClause = pid=2
                }
            }
        }
    }

See: https://github.com/TYPO3-Solr/ext-solr/issues/426

### Show devLog entries as debug message or in the TYPO3 console

You can now show the written log messages as output of the debug console in the backend or debug messages in the frontent, when you enable the following setting:

    plugin.tx_solr.logging.debugDevlogOutput = 1

NOTE: $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] needs to be configured and match.

### RST Documentation

We've ported the typoscript reference from the forge wiki into an rst format. At the same time we've enhanced the documentation with chapters about:

- Getting Started
- The Backend
- Logging
- FAQ section

If you want to contribute and bring the documentation one step further feel free to proof-read or provide additional parts as a pull request.

## Deprecations

* Typo3Environment: The usage of ApacheSolrForTypo3\Solr\Typo3Environment is deprecated and should be removed

## Outlook

In the next release (6.0) we will focus on our codesprint (11. to 13. august in frankfurt). Beside that we will focus
on issues in the issue tracker and general improvements of the codebase and documentation.

## Contributors

Like always this release would not have been possible without the help from our
awesome community. These are the contributors for this release.

(patches, comments, bug reports, review, ... in alphabetical order)

* Daniel Siepmann
* Frans Saris
* Gerald Spreer
* Hendrik Nadler
* Ingo Renner
* Marc Bastian Heinrichs
* Markus Friedrich
* Olivier Dobberkau
* Patrick Oberdorf
* Peter Kraume
* Sascha Löffler
* Timo Hund

Also a big thanks to our partners that have joined the EB2016 program:

* Bank CIC AG
* CS2 AG
* Cosmoblone GmbH
* Daniz online markting
* datenwerk innovationsagentur gmbh
* die_schnittsteller GmbH
* E-magineurs
* Fernando Hernáez Lopez
* Future Connection AG
* Hirsch & Wölfl GmbH
* hs-digital GmbH
* L.N. Schaffrath DigitalMedien GmbH
* pixolith GmbH & Co. KG
* Q3i GmbH & Co. KG
* RUAG Corporate Services AG
* ST3 Elkartea
* Star Finanz-Software Entwicklung und Vertriebs GmbH
* Stefan Galinski Interndienstleistungen
* Systime A/S
* websedit AG - Internetagentur
* Webstobe GmbH
* web-vision GmbH

Thanks to everyone who helped in creating this release!

## How to get involved

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports, and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help answering questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3
