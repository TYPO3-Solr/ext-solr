..  index:: Archive
.. _releases-11-6:

=============
Releases 11.6
=============

..  include:: HintAboutOutdatedChangelog.rst.txt


Release 11.6.5 ELTS
===================

This is a non-public security release for TYPO3 11.5 ELTS.

!!! Upgrade to Apache Solr 9.10.1
---------------------------------

Apache Solr 9.10.1 fixes several security issues, please upgrade your Apache Solr instance!

*   CVE-2025-54988: Apache Solr extraction module vulnerable to XXE attacks via XFA content in PDFs
*   CVE-2026-22444: Apache Solr: Insufficient file-access checking in standalone core-creation requests
*   CVE-2026-22022: Apache Solr: Unauthorized bypass of certain "predefined permission" rules in the RuleBasedAuthorizationPlugin

Release 11.6.4 ELTS
===================

This is a non-public maintenance release for TYPO3 11.5 ELTS, containing:

*   [FEATURE] Add arm64 platforms to docker-images and push to registry.dkd.de by Rafael Kähm (a28d4a1)

Release 11.6.3 ELTS
===================

This is a non-public maintenance release for TYPO3 11.5 ELTS, containing:

New in this release
-------------------

Apache Solr 9.8.1 support
~~~~~~~~~~~~~~~~~~~~~~~~~

EXT:solr 11.6.3 has been tested with Apache Solr 9.8.1, this version can be used without any update steps to consider.

Full list of changes
~~~~~~~~~~~~~~~~~~~~

- [TASK] Allow Apache Solr 9.8.1 by @dkd-friedrich
- [BUGFIX:P:11.6] Add check if generator is valid before traversing it by @jacobsenj and @dkd-friedrich
- [FEATURE:P:11.6] Use PHP generator to prevent processing of all available site @sfroemkenjw and @dkd-friedrich


Release 11.6.2 ELTS
===================

This is a non-public security release for TYPO3 11.5 ELTS, containing:

!!![SECURITY] Update to Apache solr 9.8.0 : CVE-2025-24814
----------------------------------------------------------

Updates EXT:solr to Apache Solr 9.8.0.

Apache Solr 9.8.0 disables the possibility to load the `jar` files with `lib` directive by default,
which was used to load jar files within the EXT:solr configsets. Apache Solr 10.0.0 will drop that functionality.
All Apache Solr libs, modules or plugins must be configured within the main server configuration files.
See: https://issues.apache.org/jira/browse/SOLR-16781

Impact:
~~~~~~~

Docker
""""""

You can wipe the volume and start the container with v. 11.6.2+ image, but that method will wipe the index as well.

See the script `EXT:solr/Docker/SolrServer/docker-entrypoint-initdb.d-as-sudo/fix-CVE-2025-24814.sh`


Other server setups
"""""""""""""""""""

You have 2 possibilities to fix that issue in your Apache Solr Server:


(PREFERRED) Migrate the EXT:solr's Apache Solr configuration
''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''


Refer to https://github.com/TYPO3-Solr/ext-solr/pull/4290/files .

Following 3 files are relevant:

*   Changes in `<Apache-Solr data dir>/configsets/ext_solr_11_6_0_elts/conf/solrconfig.xml`
*   Changes in `<Apache-Solr data dir>/solr.xml`
*   Movement from `<Apache-Solr data dir>/configsets/ext_solr_11_6_0_elts/typo3lib/solr-typo3-plugin-6.0.0.jar`

    *   to `<Apache-Solr data dir>/typo3lib/solr-typo3-plugin-6.0.0.jar`

Steps:

#.  Remove all occurrences of `<lib dir=".*` from `<Apache-Solr data dir>/configsets/ext_solr_11_6_0_elts/conf/solrconfig.xml` file.
#.  Replace in `<Apache-Solr data dir>/solr.xml` file
    the snipped

    ..  code-block:: xml

        <str name="modules">scripting</str>

    by

    ..  code-block:: xml

         <str name="modules">scripting,analytics,analysis-extras,langid,clustering,extraction,${solr.modules:}</str>
         <str name="allowPaths">${solr.allowPaths:}</str>
         <str name="allowUrls">${solr.allowUrls:}</str>

         <!-- TYPO3 Plugins -->
         <str name="sharedLib">typo3lib/</str>
#.  Move the directory from `<Apache-Solr data dir>/configsets/ext_solr_11_6_0_elts/typo3lib`

    *   to `<Apache-Solr data dir>/typo3lib`


(NOT-RECOMMENDED) Re-enable <lib> directives on Apache Solr >=9.8.0 <10.0.0
'''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''


Add following to `/etc/default/solr.in.sh` file

..  code-block:: shell

      SOLR_OPTS="$SOLR_OPTS -Dsolr.config.lib.enabled=true"

Or do that in other ways to set the `solr.config.lib.enabled=true` to sys-props of Apache Solr Server.

!!![FIX] Docker execution order issue for as-sudo tweaks
--------------------------------------------------------

This change renames the file

*   from `/docker-entrypoint-initdb.d/as-sudo-tweaks.sh`
*   to `/docker-entrypoint-initdb.d/0_as-sudo-tweaks.sh`

and moves the folder

*   from `/docker-entrypoint-initdb.d/as-sudo/`
*   to `/docker-entrypoint-initdb.d-as-sudo/`

to fix the execution order issue when setting the correct file permissions
when starting the docker container, leading to a `Operation not permitted` errors.

More details see:

*   https://github.com/TYPO3-Solr/ext-solr/issues/3837#issuecomment-2461668377.
*   https://github.com/TYPO3-Solr/ext-solr/pull/4219#issuecomment-2622600937

Impact:
~~~~~~~

This change requires adjustments in your Docker setup, only if you modified:

*   files in folder `/docker-entrypoint-initdb.d/as-sudo/`
*   file `/docker-entrypoint-initdb.d/as-sudo-tweaks.sh`.

Make sure to use:
"""""""""""""""""

*   `/docker-entrypoint-initdb.d/0_as-sudo-tweaks.sh` instead of

    *   `/docker-entrypoint-initdb.d/as-sudo-tweaks.sh`

*   `/docker-entrypoint-initdb.d-as-sudo` instead of

    *   `/docker-entrypoint-initdb.d/as-sudo/`


Minor changes & bugfixes
------------------------

*   [DOCS] Improve Solr core creation via API and other deployment parts by @dkd-kaehm & @dkd-friedrich in #41
*   [TASK] Use relative path to typo3lib in Apache Solr config by @dkd-kaehm & @dkd-friedrich in #41
*   [BUGFIX] Docker twaks as-sudo do not preserve the Docker image ENV by @dkd-kaehm & @dkd-friedrich in #41
*   [BUGFIX] Docker tests suite does not contain all logs by @dkd-kaehm & @dkd-friedrich in #41
*   [BUGFIX] docker image tests do not fail if core can not start by @dkd-kaehm & @dkd-friedrich in #41
*   [TASK] Replace "Publish to TER" to release by @dkd-kaehm in #38

Release 11.6.1 ELTS
===================

This is a non-public maintenance release for TYPO3 11.5.

Full list of changes
--------------------

* [TASK] Integrate TYPO3 11.5 ELTS by @dkd-kaehm & @dkd-friedrich
* [TASK] Prepare release-11.6.x ELTS by @dkd-friedrich
* [BUGFIX] NPE in Index Queue module when no site is selected by @adamkoppede
* [FEATURE] Add timeframe filter to statistics module by @BastiLu

Release 11.6.0
==============

We are happy to release EXT:solr 11.6.0.
The focus of this release has been on Apache Solr upgrade to v9.7.0.

**Important**: This version is installable with TYPO3 11 LTS on v11.5.14+ only and contains some breaking changes, see details below.

New in this release
-------------------

!!! Upgrade to Apache Solr 9.7.0
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

This release requires Apache Solr v9.7.0+.

Along with the compatibility to Solr 9.7 the dependency to SOLR_ENABLE_STREAM_BODY is removed.

Full list of changes
~~~~~~~~~~~~~~~~~~~~

* !!![BUGFIX:BP:11.6] Queue check considers indexing configuration by @dkd-friedrich
* !!![TASK:BP:11.6] Introduce queue and queue item interfaces by @dkd-friedrich
* [TASK:BP:11.6] Consider queue initialization status by @dkd-friedrich
* !!![TASK:BP:11.6] Introduce specific EXT:solr exceptions  by @dkd-friedrich
* [TASK] Prepare release-11.6.x for TYPO3 11.5 LTS by @dkd-kaehm
* [TASK] sync the CI stuff from main branch into 11.6.x by @dkd-kaehm
* [TASK] Activate PHPStan  by @dkd-friedrich
* [TASK] Use Apache Solr 9.2 for EXT:solr 11.6  by @dkd-friedrich
* [FEATURE] Introduce TYPO3_SOLR_ENABLED_CORES docker env variable by Christoph Lehmann
* [TASK] Make it possible to change solr unix GID:UID on docker image… by @dkd-kaehm
* [TASK] revert unnecessary changes on Apache Solr 9.2 upgrade by @dkd-kaehm
* !!![TASK] Upgrade to Apache Solr 9.3.0 by @dkd-friedrich
* [BUGFIX] Fix result highlighting fragment size by @dkd-friedrich
* [TASK] Update to Solr 9.5 by @dkd-friedrich
* New Crowdin updates 2024.07.03 by @dkd-kaehm
* [TASK] Upgrade to Apache Solr 9.7 by @dkd-friedrich


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

* Adam Koppe
* @BastiLu
* Christoph Lehmann
* @itzonban
* Jens Jacobsen
* Markus Friedrich
* Rafael Kähm
* Stefan Frömken

Also a big thank you to our partners who have already concluded one of our new development participation packages for Apache Solr EB for TYPO3 11 LTS (Feature, Maintenance, ELTS):

*   .hausformat
*   3m5. Media GmbH
*   3m5. Media GmbH 3m5. Media GmbH
*   abteilung_digital GmbH
*   ACO Ahlmann SE & Co. KG
*   Agence E-magineurs
*   Agenda d.o.o.
*   AgenturWebfox GmbH
*   Amedick & Sommer Neue Medien GmbH
*   Ampack AG
*   Atol CD
*   Ausy
*   Autorité des Marchés Financiers (Québec)
*   avenit AG
*   b13 GmbH
*   bei Intersim AG
*   brandung GmbH Oliver Krause
*   Bytebetrieb GmbH & Co. KG
*   Canton de Neuchâtel - SIEN
*   CARL von CHIARI GmbH
*   chiliSCHARF GmbH
*   clickstorm GmbH
*   clickstorm GmbH
*   co-operate Wegener & Rieke GmbH
*   Columbus Interactive GmbH
*   cosmoblonde GmbH
*   creativ clicks GmbH
*   cyperfection GmbH
*   digit.ly
*   DMK E-BUSINESS GmbH
*   dörler engineering services e.U.
*   Earlybird GmbH & Co KG
*   elancer-team GmbH
*   eulenblick Kommunikation und Werbung
*   F7 Media GmbH
*   Fachagentur Nachwachsende Rohstoffe fnr.de
*   Fachhochschule Erfurt
*   Fourdegrees GbR
*   FTI Touristik GmbH
*   Getdesigned GmbH
*   GFE Media GmbH
*   graphodata GmbH
*   grips IT GmbH
*   hiroki digital GmbH
*   Hirsch & Wölfl GmbH
*   Hob by Horse GmbH
*   Hochschule Furtwangen
*   Hochschule Koblenz - Standort Remagen
*   HSPV NRW
*   in2code
*   INOTEC Sicherheitstechnik GmbH
*   internezzo ag
*   IW Medien GmbH
*   jweiland
*   Kassenärztliche Vereinigung Rheinland-Pfalz
*   kraftwerk Agentur für neue Kommunikation GmbH
*   Kreis Euskirchen
*   Kunstuniversität Graz
*   L.N. Schaffrath DigitalMedien GmbH
*   La Financière agricole du Québec
*   Landeskriminalamt Thüringen
*   Leitgab Gernot
*   Leuchtfeuer Digital Marketing GmbH
*   Lingner Consulting New Media GmbH
*   LOUIS INTERNET GmbH
*   Marketing Factory
*   media::essenz
*   medien.de mde GmbH
*   MEDIENHAUS der Evangelischen Kirche in Hessen und Nassau GmbH
*   mehrwert intermediale kommunikation GmbH
*   Meridium Technologies
*   MOSAIQ GmbH
*   NEW.EGO GmbH
*   novotegra GmbH (BayWa r.e. AG)
*   Overlap GmbH & Co KG
*   peytz.dk
*   pick2webServices Magdalena Rybak
*   pietzpluswild GmbH
*   Pixelant
*   Plan Software GmbH
*   Plan.Net France
*   ProPotsdam GmbH
*   Provitex GmbH
*   queo GmbH
*   Québec.ca gouv.qc.ca
*   ressourcenmangel integral gmbh
*   rms. relationship marketing solutions GmbH
*   RR Anwendungsentwicklung Hr. Roskothen
*   Sandstein Neue Medien GmbH
*   Schoene neue kinder GmbH
*   seam media group gmbh
*   SITE'NGO
*   Snowflake Productions GmbH
*   SOS Software Service GmbH
*   Stadtverwaltung Villingen-Schwenningen
*   statistik.gv.at Bundesanstalt Statistik Österreich
*   Stämpfli AG
*   systime.dk
*   Talleux & Zöllner GbR
*   tirol.gv.at
*   toumoro.com
*   TWT reality bytes GmbH
*   Typoheads GmbH
*   UDG Rhein-Main GmbH
*   UEBERBIT GmbH
*   unternehmen online GmbH Co. KG (UO)
*   Verband der Vereine Creditreform e.V.
*   VisionConnect.de
*   visol digitale Dienstleistungen GmbH
*   visuellverstehen GmbH
*   WACON Internet GmbH
*   webconsulting business services gmbh
*   Webtech AG
*   Werbeagentur netzpepper
*   werkraum Digitalmanufaktur GmbH
*   wow! solution
*   zimmer7 GmbH

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



