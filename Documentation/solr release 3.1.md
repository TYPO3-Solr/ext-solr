# Apache Solr for TYPO3 version 3.1 released

We're happy to announce the release of Apache Solr for TYPO3 (EXT:solr) version 3.1.0. With this release we now support TYPO3 CMS versions 6.2LTS and 7.6LTS together with Apache Solr version 4.10.

## New in this Release

### Support of TYPO3 7.6 LTS:

We added support for TYPO3 CMS 7.6LTS while still supporting version 6.2LTS to allow an easy upgrade. Support for TYPO3 version 4.5LTS has been dropped with this release. The next release will likely require at least TYPO3 7.6LTS.

On the code side we now use PSR-2 coding style like in TYPO3 7.6LTS. This way it is easy for contributors and developers to stick with a common coding style. For contributions PSR-2 style is also enforced through automatic checks now.

Overall we resolved over 40 issues and added almost 700 commits.

### Apache Solr 4.10

The extension comes with an installer that installs Apache Tomcat 8.0.29 and Apache Solr 4.10.4. Please make sure to update to Apache Solr 4.10.4 and the new Solr schema and configuration provided by the extension to make sure the extension works as intended.

### Manage Stopwords from the Backend

With EXT:solr 3.0 we introduced an all new backend module to manage the Index Queue, check index data, and manage synonyms. With this release we're adding a new sub module to allow editing stopwords.

Stopwords are usually used to discard common words when indexing. This can increase relevance of documents.

### Introducing PHP Namespaces

Our complete codebase is now namespaced and uses root namespace "\ApacheSolrForTypo3\Solr\" for all classes. We also added a class map to provide backwards compatibility. Nevertheless we suggest updating your extensions to use the namespaced class names if you use them.

### Introducing Travis CI

For a long time we already had a small unit test suite. However, it was not kept up-to-date, tests weren't executed automatically and generally in a state that left room for improvement. Starting with this release we will focus on increasing test coverage and keeping them up-to-date.

The unit test suite is now executed automatically for each Pull Request through Travis CI (https://travis-ci.org/TYPO3-Solr/ext-solr). Currently we run tests against TYPO3 version 6.2 LTS, 7.6 LTS, and dev-master with the same PHP versions as TYPO3 CMS, PHP 5.3-5.6.

Additionally Travis CI also checks for PSR-2 compliance and will fail a PR in case the code does not fit that coding standard. 

In case a Pull Request is rejected by Travis CI you can follow the link to the build and inspect its output to see what needs to be fixed.

## General Changes

### HTML escaping after retrieval from solr

The data ist now esapced right after the retrieval from Solr. In rare cases when you need to store HTML in Solr documents you can use the following configuration to mark these fields as trusted fields and skip the escaping:

plugin.tx_solr.search.trustedFields = my_first_html_field,my_second_html_field

### Synonym handling (Lowercasing and Evaluation at Index- and Querytime)

Synonyms are now handled at query and index time. The advantage is, that when editing synonyms from the backend module those changes become effective immediatly.

### Removed page browser dependency

As we were preparing to be compatible with TYPO3 7.6LTS we found that there is no compatible version of EXT:pagebrowse. We used that extension to provide the page browser in results listings. To become compatible with TYPO3 7.6LTS we now have integrated the page browser into EXT:solr itself. So if you are not using EXT:pagebrowse for anything else you can safely remove the extension from your installation.

## Installation and Updating

To install EXT:solr version 3.1.0 you will need either TYPO3 6.2LTS or TYPO3 7.6LTS. On the Solr side we require Apache Solr 4.10.4, which requires Java 7.

When updating an existing installation to EXT:solr 3.1.0 please make sure to update to Apache Solr 4.10.4 including the new schema and configuration files. The extension comes with a script that will install a working setup for you automatically. The script can be found in EXT:solr/Resources/Install/.

In rare cases you may have to re-initialize the Solr connections. You can do so from TYPO3's clear cache menu in the top toolbar.
Because of the changes made to the schema you will need to re-index your site.

Make sure to check the system status report for any warnings or errors.

### TypoScript Paths changed

During the migration to namespace we also had to change the TypoScript paths:

   tx_solr_pi_results => tx_solr_PiResults_Results
   tx_solr_pi_search => tx_solr_PiSearch_Search
   tx_solr_pi_frequentsearches  => tx_solr_PiFrequentSearches_FrequentSearches

If you're building custom TypoScript content objects referencing solr plugin configurations you should make to update these as well.

## Outlook

With this release out the door we will focus on updating the add-on extensions like EXT:solrgrouping, EXT:tika, and EXT:solrfal for file indexing, these should be available soon, too.

The next release within the 3.x series will likely require at least TYPO3 7.6LTS and might as well arrive soon as there are no bigger changes planned yet besides dropping support for TYPO3 6.2LTS.

After that we will start working on the long-awaited move to Fluid as the template engine for version 4.0.

## Contributors

Like always this release would not have been possible without the help from our awesome community. These are the contributors for this release.

(patches, comments, bug reports, review, ... in alphabetical order)

* Alexander Stehlik
* Andreas Allacher
* Andreas Fernandez
* Andreas Wolf
* Daniel Siepmann
* Dmitry Dulepov
* Frank Nägler
* Frans Saris
* Gordon Brüggemann
* Hans Höchtl
* Heiko Hardt
* Hendrik Nadler
* Ingo Renner
* Javn Wagner
* Jens Jacobsen
* Jigal van Hemert
* Joschi Kuphal
* Josef Florian Glatz
* Kevin von Spiczak
* Marc Bastian Heinrichs
* Markus Friedrich
* Markus Günther
* Markus Sommer
* Markus Kobligk
* Maxime Lafontaine
* Michael Knabe
* Michiel Roos
* M. Tillmann
* Olivier Dobberkau
* Peter Kraume
* Phuong Doan
* Romain Canon
* Sascha Affolter
* Sascha Egerer
* Sascha Nowak
* Sebastian Enders
* Soren Malling
* Stefan Galinski
* Stefan Neufeind
* Steffen Müller
* Steffen Ritter
* Thomas Heilmann
* Thomas Janke
* Timo Schmidt
* Witali Rott
* Thomas Heilmann

Thanks to everyone who helped in creating this release!

### Introducing Timo Schmidt

Last but not least we would like to introduce Timo Schmidt. Timo recently joined dkd to work on Apache Solr for TYPO3 in support and development. If you've been active in our Slack channel or followed our GitHub commits you may have been in contact with Timo already. We're looking forward to working with Timo.

## How to get involved

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports, and feature requests on GitHub (https://github.com/TYPO3-Solr/ext-solr/issues)
* Ask or help answering questions in our Slack channel: https://typo3.slack.com/messages/ext-solr/
* Provide patches through Pull Request or review and comment on existing Pull Requests (https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to http://www.typo3-solr.com/ or call dkd to sponsor the ongoing development of Apache Solr for TYPO3

