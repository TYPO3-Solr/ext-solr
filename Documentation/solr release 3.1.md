# Apache Solr for TYPO3 version 3.1 released

## New in this Release

### Support of TYPO3 7.6 LTS:

This release support TYPO3 Version 6.2.15 LTS and 7.6.0 LTS this allows you a smooth migration from 6.2.15 to 7.6.

### Solr 4.10

We now recommend and support the usage of version 4.10 of solr.

### Maintain Stopword in the Backend

You can now add stopwords in the backend module that should be excluded from the search.

### Introducing PHP Namespaces

Our complete codebase is now namespaced and useses the namespace "ApacheSolrForTypo3\Solr\.." for all internal classes.

### Introducing Travis-CI

The existing unit test suite is now on travis-ci (https://travis-ci.org/TYPO3-Solr/ext-solr) every commit and pull request is now tested against "6.2.15 / 7.6.0 and dev-master". We'll improve the test suite contin

## General Changes

### HTML escaping after retrieval from solr

The data ist now esapced right after the retrieval from solr. In the rarely case when you need to store HTML in the solr
server you can use the following configuration to marke these fields as trusted fields and skip the escaping:

plugin.tx_solr.search.trustedFields = my_first_html_field,my_second_html_field

### Synonym handling (Lowercasing and Evaluation at Index- and Querytime)

Synonyms are now handled at query and index time. The advantage is, that changes in the backend allready have an effect
when they have been added. At the same time, synonyms get lowercased when they are stored in solr and before they get
evaluated in the Solr schema.

Note: To see the whole impact on the result set the re indexing needs to be finished.


### TypoScript Paths changed

During the migration to namespace we also decided to change the TypoScript pathes:

   tx_solr_pi_results => tx_solr_PiResults_Results
   tx_solr_pi_search => tx_solr_PiSearch_Search
   tx_solr_pi_frequentsearches  => tx_solr_PiFrequentSearches_FrequentSearches

You need to change this, when you update.

### Remove page browser

@TODO

## Updating

When you want to use ext_solr 3.1.0 you need to run Version 4.10.4 of Apache Solr. You can use the install script
in "Resources/Install" to install Apache Solr and Tomcat.

The install script download and installs Apache Solr with the following components:

* Tomcat (Version: 8.0.29)
* Apache Solr (Version: 4.10.4)
* Solr TYPO3 Plugin (Version: 1.3.0)

It is required to have JAVA 7 or later installed on your server.

After updating from 3.0.x you need to:

* Clear all caches
* Re-initialize the solr connections
* Check your custom TypoScript if and adopt it to the new pathes

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

## How to get involved

There are many ways to get involved into ext_solr:

* Submit bug reports, bug fixes and feature requests with an issue on github (https://github.com/TYPO3-Solr/ext-solr/issues)

* Ask or Answer in our slack channel: https://typo3.slack.com/messages/ext-solr/

* Implement as task or create a pull request for features that you have created or review existing pull requests and comment them (https://github.com/TYPO3-Solr/ext-solr/pulls)

* Go to http://www.typo3-solr.com/ or call DKD to sponsor the ongoing development of solr for TYPO3

