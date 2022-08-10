.. include:: ../Includes.rst.txt


.. _releases-11-5:

============================
Apache Solr for TYPO3 11.5.0
============================

We are happy to release EXT:solr 11.5.0.
The focus of this release has been on TYPO3 11 LTS compatibility.

#standwithukraine #nowar

**Important**: This version is installable with TYPO3 11 LTS only and contains some breaking changes, see details below.

New in this release
===================

Support of TYPO3 11 LTS
-----------------------

With EXT:solr 11.5 we provide the support of TYPO3 11 LTS.

Please note that we require at least TYPO3 11.5.14, as this version contains some change concerning the usage of local TypoScriptFrontendController objects that are solving some issues during indexing.


Bootstrap 5.1
-------------

The default templates provided by EXT:solr were adapted for Bootstrap 5.1.

The templates are also prepared to display some icons with Bootstrap Icons, but the usage is optional and the icons are no longer provided with EXT:solr as the former Glyphicons were.


Custom field processors
-----------------------

fieldProcessingInstructions can be used for processing values during indexing, e.g. timestampToIsoDate or uppercase. Now you can register and use your own field processors via:
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['fieldProcessor']['yourFieldProcessor'] = ACustomFieldProcessor::class;

Custom processors have to implement interface ApacheSolrForTypo3\Solr\FieldProcessor\FieldProcessor.


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Christoph Lehmann
* Christopher Schnell
* garfieldius
* Guido Schmechel
* Henrik Elsner
* Lars Tode
* Marc Bastian Heinrichs
* Mario Lubenka
* Marcus Balasch
* Markus Friedrich
* Marcus Schwemer
* Nicola Widmer
* Rudy Gnodde
* Sascha Egerer
* Sebastian Hofer
* Soren Malling
* twojtylak
* Tobias Schmidt


Also a big thank you to our partners who have already concluded one of our new development participation packages such as Apache Solr EB for TYPO3 11 LTS (Feature), Apache Solr EB for TYPO3 10 LTS (Maintenance)
or Apache Solr EB for TYPO3 9 ELTS (Extended):

* .hausformat GmbH
* ACO Ahlmann SE & Co. KG
* avenit AG
* b13 GmbH
* Cobytes B.V.
* Connetation Web Engineering GmbH
* cyperfection GmbH
* DVT - Daten-Verarbeitung-Tirol GmbH
* Earlybird GmbH & Co KG
* elancer-team GmbH
* FONDA GmbH
* GFE Media GmbH
* Hochschule Niederrhein
* i-fabrik GmbH
* in2code GmbH
* internezzo ag
* Intersim AG
* IW Medien GmbH
* Jochen Weiland
* Landeskriminalamt Thüringen
* L.N. Schaffrath DigitalMedien GmbH
* Leitgab Gernot
* LOUIS INTERNET GmbH
* Marketing Factory Consulting GmbH
* medien.de mde GmbH
* MEDIA::ESSENZ
* mehrwert intermediale kommunikation GmbH
* Neue Medien GmbH
* NEW.EGO GmbH
* novotegra GmbH
* Pädagogische Hochschule Karlsruhe
* ProPotsdam GmbH
* Provitex GmbH
* Proud Nerds
* Québec.ca
* seam media group gmbh
* SITE'NGO
* Stämpfli AG
* Studio 9 GmbH
* techniConcept Sàrl
* TOUMORØ
* WACON Internet GmbH
* we.byte GmbH
* wegewerk GmbH
* werkraum Digitalmanufaktur GmbH
* WIND Internet

How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help or answer questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

Support us by becoming an EB partner:

https://shop.dkd.de/Produkte/Apache-Solr-fuer-TYPO3/

or call:

+49 (0)69 - 2475218 0


