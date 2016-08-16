# Apache Solr for TYPO3 version 5.1 released

We're happy to announce the release of Apache Solr for TYPO3 (EXT:solr) version 5.1.0. This release ships a few bugfixes and smaller features

## New in this Release

The focus of this release was to provide the new features from the codesprint and bugfixes from the issue tracker. Beside that, we've added a variants feature that could be used together with the upcomming solrfal release 3.2 as one approach to avoid duplicated files in the search frontend.


### Document Variants

This feature adds a new optional field variantId that is filled with type/uid and can be used for collapsing by default.

The following example shows the required typoscript configuration that is needed:

    plugin.tx_solr.search {
        variants = 0
        variants {
            expand = 1
            variantField = variantId
            limit = 10
        }
    }

The collapsing can be used to collapse on numeric and string fields, text fields are not supported for collapsing by Solr. 

**Note**: You need to update your solrconfig and schema to use the new field variantId

Details: https://github.com/TYPO3-Solr/ext-solr/pull/550

### Improve Documentation

During the transition from forge to github, we've moved the rest of the documentation to our rst documentation.

Details: https://github.com/TYPO3-Solr/ext-solr/pull/547

### Backend statistics

During the codesprint in Frankfurt a new statistics module was added for that backend that allows you to get some usefull insights 
of your search installation.

* Which terms are the most frequent search terms?
* Which terms are frequently searched but deliver no result?

### UX Improvements

During the codesprint several UX improvements in the backend module have been done:

**Replace content element wizard icon with svg icon:**

* Replace content element wizard icon with svg icon

Detail: https://github.com/TYPO3-Solr/ext-solr/pull/581

**Make init connections icon use SVG:** 

* Adds new SVG icon for init connection

Details: https://github.com/TYPO3-Solr/ext-solr/pull/566

**Backend module layout fixes**

* Makes sure everything is aligned properly
* Removes some whitespace, giving the module content more room


Details: https://github.com/TYPO3-Solr/ext-solr/pull/575

### Bugfix: absRefPrefix auto in cli mode with webroot different from PATH_site

When you are using absRefPrefix auto and index in cli mode and you webroot differs from PATH_site you have the possibility to configure a 
webroot in the scheduler task to make absRefPrefix=auto work in this case

Details: https://github.com/TYPO3-Solr/ext-solr/pull/495

### Bugfix: Keep selected site in scheduler task

When a site in the scheduler task was selected the selection could get lost. 

Details: https://github.com/TYPO3-Solr/ext-solr/pull/557

## Outlook

In the next release (6.0) we will focus on the integration of the latest Apache Solr Version (currently 6.1.0)

## Contributors

Like always this release would not have been possible without the help from our
awesome community. These are the contributors for this release.

(patches, comments, bug reports, review, ... in alphabetical order)

* Daniel Siepmann
* Ingo Renner
* Steffen Ritter
* Timo Hund
* Thomas Hohn
* Thomas Scholze

Also a big thanks to our partners that have joined the EB2016 program:

* Bank CIC AG
* Bitmotion GmbH
* Citkomm services GmbH
* CS2 AG
* Cosmoblonde GmbH
* Daniz online markting
* datenwerk innovationsagentur gmbh
* die_schnittsteller GmbH
* E-magineurs
* Fernando Hernáez Lopez
* Future Connection AG
* Gernot Leitgab
* Hirsch & Wölfl GmbH
* hs-digital GmbH
* IHK Neubrandenburg
* L.N. Schaffrath DigitalMedien GmbH
* mehrwert intermediale kommunikation GmbH
* netlogix GmbH & Co. KG
* pixolith GmbH & Co. KG
* Q3i GmbH & Co. KG
* RUAG Corporate Services AG
* ST3 Elkartea
* Star Finanz-Software Entwicklung und Vertriebs GmbH
* Stefan Galinski Interndienstleistungen
* Systime A/S
* SYZYGY Deutschland GmbH
* web-vision GmbH
* websedit AG - Internetagentur
* Webstobe GmbH
* WIND Internet

Thanks to everyone who helped in creating this release!

## How to get involved

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports, and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help answering questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3
