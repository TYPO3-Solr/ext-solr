..  index:: Releases
.. _releases-11-6:

=============
Releases 11.6
=============

..  include:: HintAboutOutdatedChangelog.rst.txt

Release 11.6.6 ELTS
===================

This is a security release for TYPO3 11.5 ELTS.

!!! All earlier 11.6.x releases stay vulnerable and will not be re-published
-----------------------------------------------------------------------------

11.6.6 is the only release of this branch that is published publicly.
Releases 11.6.0 to 11.6.5 are affected by the CVEs fixed in this release and will **not** be
re-published with a fix, so there is no patched 11.6.5 or earlier to move to — upgrade to 11.6.6.

!!! Solarium raised to 6.2.4
-----------------------------

``solarium/solarium`` is raised from 6.2.3 to 6.2.4. This branch needs it to enforce the fix in PHP at all:
6.2.3 ships no ``HighlightingInterface``, no ``setOffsetSource()`` and no ``setFragsizeIsMinimum()``,
and its request builder emits ``h1.method`` instead of ``hl.method``, so the Unified Highlighter
never reached Solr. Composer resolves this on update; installations that pin ``solarium/solarium``
themselves must allow 6.2.4.

!!! Recommendation: align existing Solr volumes with the new configset
----------------------------------------------------------------------

The ``ext_solr_11_6_0_elts`` configset now sets the Unified Highlighter as default on both the ``/select`` and ``/browse`` request handlers.
Solr volumes created from older configsets default to the legacy highlighter and remain vulnerable to the ``FieldExistsQuery`` HTTP 500 oracle
when queried directly (bypassing EXT:solr).
Run the bundled migration script against the existing configset to align the defaults;
the script is idempotent and writes a ``solrconfig.xml.Backup-SST-235567`` backup next to the modified file:

*   ``Docker/SolrServer/docker-entrypoint-initdb.d-as-sudo/fix-SST-235567-2026050810000025-highlighter-defaults.sh``

EXT:solr itself enforces the Unified Highlighter unconditionally in PHP, so this configset alignment is a defence-in-depth measure
for clients that query Solr directly.


!!! New: TypoScript settings for query-syntax handling
-------------------------------------------------------

Two new TypoScript settings govern how user input on ``tx_solr[q]`` is parsed:

* ``plugin.tx_solr.search.query.userFields`` — whitelist of fields a Solr field-selector (``field:value``) may target.
  By default derived from ``query.queryFields``; selectors against other fields are now treated as literal terms and silently miss.
  Sites that rely on selectors against non-``qf`` fields must extend the whitelist via a scalar override or the ``add`` / ``remove`` sub-keys.
* ``plugin.tx_solr.search.query.allowSolrOperatorSyntax`` — toggle for operator-syntax passthrough.
  Default ``1`` keeps the documented ``+ - && || ! * ?`` UX functional; set to ``0`` for strict mode (additionally escapes ``| & ;``).
  Selector, range and grouping characters (``: [ ] ( ) { } ^ " ~ \ /``) are always escaped regardless.

See :ref:`configuration.reference.solrsearch` for full reference details.


!!! Breaking: multi-value cObjs now use JSON transport
------------------------------------------------------

The ``SOLR_MULTIVALUE``, ``SOLR_RELATION`` and ``SOLR_CLASSIFICATION`` content objects now return their
multi-value payload as ``json_encode($array)`` instead of ``serialize($array)``, and the indexer decodes
it with ``json_decode()`` instead of ``unserialize()``.
Because ``json_decode()`` never reconstructs PHP objects, the indexer can no longer be turned into a PHP
object-injection sink by an attacker who can influence an indexed record field.

Third-party content objects that returned ``serialize($array)`` for a multi-value Solr field must switch
to ``json_encode($array)``; no other change is required.
The same applies to extensions registering a ``SerializedValueDetector`` through
``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue']``: the flagged field is now
JSON-decoded, so its content object must emit JSON as well.


!!! Security: additionalFilters can no longer preempt the siteHash filter
-------------------------------------------------------------------------

Request-provided ``tx_solr[additionalFilters]`` could register a named ``siteHash`` filter that ``AbstractQueryBuilder::useFilter()`` refused to overwrite,
so the system ``siteHash`` filter added later by ``AccessComponent`` was dropped.
In a shared-Solr-core multi-site installation this let an anonymous visitor of one site read public documents of another site sharing the same core (CVE-2026-56094).

EXT:solr now strips the reserved filter names ``siteHash`` and ``access`` from request-provided ``additionalFilters`` before they reach the query,
and applies the system ``siteHash`` filter with remove-then-set semantics so request input can no longer preempt it.
Filters that integrators set server-side — TypoScript ``plugin.tx_solr.search.query.filter.``, plugin/FlexForm, or PSR-14 events — are unaffected.

**Impact for integrators:** a frontend request can no longer override ``siteHash`` (or ``access``) through ``tx_solr[additionalFilters]``.
Cross-site search must be configured server-side via ``plugin.tx_solr.search.query.allowedSites`` as documented in :ref:`configuration.reference.solrsearch`.


!!! Security: detail view enforces site and access restrictions (CVE-2026-56093)
--------------------------------------------------------------------------------

The ``detail`` action of the ``pi_results`` plugin looked up a document by its ``documentId`` without applying the current site's ``siteHash`` filter
or the frontend user-group access filter.
An anonymous visitor who knew or guessed a valid ``documentId`` could therefore retrieve access-restricted documents through the detail view
— a path that was less restricted than the regular search.

The direct ``documentId`` lookup now applies the same ``siteHash`` and frontend user-group filters as the normal search path.
A ``documentId`` that resolves to no accessible document — unknown, from another site, or restricted for the current visitor
— yields the site's configured 404 page.
The response is uniform, so it cannot be used to probe whether a restricted document exists.

As defence in depth, review whether your templates still expose ``data-document-id`` and mask it where the document id should not be publicly visible.


!!! Security: page indexer no longer forges access fields on page records (CVE-2026-56092)
-------------------------------------------------------------------------------------------

During a page-indexer sub-request, EXT:solr forced ``fe_group`` and ``extendToSubpages`` to public
values on every ``pages`` record it touched, so the indexer itself would not be blocked by access
restrictions.

On TYPO3 11 those forged values were written into the shared ``rootline`` cache. No anonymous-access
bypass could be reproduced on this branch: the override only applies while a translated view is being
resolved, whereas a visitor of an untranslated page is served — and cached — from the original record,
and where a translation does exist the language overlay replaces the forged fields with the translated
page's own values. The corrupted entries are wrong regardless, and a manually corrupted entry *is*
honoured by the frontend access check, so this is fixed rather than tolerated. On TYPO3 13 the same
override reached the entries visitors do read, which is where the reported disclosure occurred.

The indexer no longer forges these fields. The access bypass it needs during indexing was already
provided safely, without touching any persisted cache, by EXT:solr's other, unaffected mechanisms.

**Flush the rootline cache after updating.** Entries written by earlier 11.6.x releases keep the forged
values until they are rebuilt.


All Changes
-----------

*   [DOCS] Fix 11.6 docs metadata, archive Releases 11.2, align index directives by @dkd-kaehm in `294833d7a <https://github.com/TYPO3-Solr/ext-solr/commit/294833d7ae28f6022bcef6e3f88becb0e3c145df>`_
*   [SECURITY] Fix CVE-2026-56096 — close FVH FieldExistsQuery HTTP 500 oracle by @dkd-kaehm in `2ca0e8b47 <https://github.com/TYPO3-Solr/ext-solr/commit/2ca0e8b47ee36f2e06237b1db6235495b5151be1>`_
*   [SECURITY] Fix CVE-2026-56096 — edismax uf whitelist by @dkd-kaehm in `fc0381c96 <https://github.com/TYPO3-Solr/ext-solr/commit/fc0381c964674f576bf5ff2bd48b0694613426c1>`_
*   [SECURITY] Fix CVE-2026-56096 — escape user query syntax by @dkd-kaehm in `9cc887eb4 <https://github.com/TYPO3-Solr/ext-solr/commit/9cc887eb4a4b3f3118c541044efc527679539758>`_
*   !!![SECURITY] Fix CVE-2026-56095 — JSON transport for multi-value cObjs by @dkd-kaehm in `6fd773ed7 <https://github.com/TYPO3-Solr/ext-solr/commit/6fd773ed73cb9f32d2f58f14ac4d52454a00a47f>`_
*   [TASK] fix Github Actions warnings by @dkd-kaehm in `b663d4ff8 <https://github.com/TYPO3-Solr/ext-solr/commit/b663d4ff8c947050756983909aa4ea40e0206b9b>`_
*   [SECURITY] Fix CVE-2026-56094 — Prevent request additionalFilters from preempting siteHash filter by @dkd-kaehm in `114b4a90f <https://github.com/TYPO3-Solr/ext-solr/commit/114b4a90f77099c2c458ecb67312f44fbe3aed87>`_
*   [SECURITY] Fix CVE-2026-56093 — Enforce siteHash and access filters in detailAction lookup by @dkd-kaehm in `a30973356 <https://github.com/TYPO3-Solr/ext-solr/commit/a30973356ea6b9a0f86ff0c39b6d2c35fb7be4de>`_
*   [SECURITY] Fix CVE-2026-56092 — Stop rootline cache poisoning via forged fe_group/extendToSubpages by @dkd-kaehm in `40ab4e9b8 <https://github.com/TYPO3-Solr/ext-solr/commit/40ab4e9b8e82519afe66eb9be4f9ce175c415806>`_
*   [BUGFIX] Check rootline for feGroups from page with extendToSubpages set by Tim Horstmann in `33698d117 <https://github.com/TYPO3-Solr/ext-solr/commit/33698d117a6d083a9e2f63131ebb03ad7517d770>`_
*   [BUGFIX] Log TSFE initialization failures instead of swallowing them by @kitzberger in `e0658a644 <https://github.com/TYPO3-Solr/ext-solr/commit/e0658a6441d38bbe07a92befd52738dc04f5bbf4>`_
*   [BUGFIX] Fallback for failed TSFE initialization when determining isAllowedPageType() by @kitzberger in `fd8fd96a5 <https://github.com/TYPO3-Solr/ext-solr/commit/fd8fd96a5ac4f25281ac574b378481dc7d1b285f>`_


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


