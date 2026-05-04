.. _releases-13-0:

=============
Releases 13.0
=============

..  include:: ../HintAboutOutdatedChangelog.rst.txt

Release 13.0.4
==============

Announcing the last 13.0.x maintenance release for TYPO3 12 LTS.
The release-13.0.x branch is now closed—no further 13.0.x releases will be issued.
Next up: TYPO3 13.1.0, arriving with integrated AI features.

- Fix bug for phrase search with slops, bigram and trigram by Florian Rival `3f52d30ff <https://github.com/TYPO3-Solr/ext-solr/commit/3f52d30ff>`_
- [TASK] 13.0.x-dev Bump solr from 9.9.0 to 9.10.0 in /Docker/SolrServer by Dependabot `d7f338627 <https://github.com/TYPO3-Solr/ext-solr/commit/d7f338627>`_
- [BUGFIX] Fix CS issues by Markus Friedrich `a4f3330c8 <https://github.com/TYPO3-Solr/ext-solr/commit/a4f3330c8>`_
- [TASK] Allow Apache Solr 9.9.0 by Markus Friedrich `7deb9ea1e <https://github.com/TYPO3-Solr/ext-solr/commit/7deb9ea1e>`_
- [BUGFIX] PageFieldMappingIndexer: avoid undefined array key via null coalescing (refs #4395) by David Retzer `b788000c9 <https://github.com/TYPO3-Solr/ext-solr/commit/b788000c9>`_
- [BUGFIX] Unicode characters vs. statistics feature by Philipp Kitzberger `5df9deacf <https://github.com/TYPO3-Solr/ext-solr/commit/5df9deacf>`_
- [BUGFIX] Allow initializer interface in event by Markus Friedrich `56493ee4d <https://github.com/TYPO3-Solr/ext-solr/commit/56493ee4d>`_
- [FEATURE] calculate site hash by site-identifier strategy by setting by Rafael Kähm `6860df955 <https://github.com/TYPO3-Solr/ext-solr/commit/6860df955>`_
- [BUGFIX] set site field on record documents in same way as on pages by Rafael Kähm `1a7d1ff68 <https://github.com/TYPO3-Solr/ext-solr/commit/1a7d1ff68>`_
- [FEATURE] add typo3Context[_stringS] and domain[_stringS] fields to documents by Rafael Kähm `a75d237d1 <https://github.com/TYPO3-Solr/ext-solr/commit/a75d237d1>`_
- [BUGFIX] Site check fails in Tsfe fails by Markus Friedrich `ca4cf26ae <https://github.com/TYPO3-Solr/ext-solr/commit/ca4cf26ae>`_
- [FEATURE] Make EXT:solrdebogtools plug-and-play installable by Rafael Kähm `816877a99 <https://github.com/TYPO3-Solr/ext-solr/commit/816877a99>`_
- [BUGFIX] Unable to register a custom facet package with FacetRegistry by Benoit Chenu `7aea09a10 <https://github.com/TYPO3-Solr/ext-solr/commit/7aea09a10>`_
- [BUGFIX] Don't re-instantiate TypoScript in FE/Search context by Rafael Kähm `9cd2ddc95 <https://github.com/TYPO3-Solr/ext-solr/commit/9cd2ddc95>`_
- [TASK] add test case for hidden translation in content-fallback and strict mode by Rafael Kähm `337967213 <https://github.com/TYPO3-Solr/ext-solr/commit/337967213>`_
- [BUGFIX] Delegate and adjust TYPO3 core context within indexing stack by Rafael Kähm `a5aefa8a4 <https://github.com/TYPO3-Solr/ext-solr/commit/a5aefa8a4>`_
- [TASK] add missing codes to thrown exceptions by Rafael Kähm `ea5bb914c <https://github.com/TYPO3-Solr/ext-solr/commit/ea5bb914c>`_
- [TASK] Prepare release-13.0.x branch by Rafael Kähm `040a47427 <https://github.com/TYPO3-Solr/ext-solr/commit/040a47427>`_
- [BUGFIX] don't use pages uid 0 via l10n_parent by Rafael Kähm `20be06666 <https://github.com/TYPO3-Solr/ext-solr/commit/20be06666>`_
- [TASK] 13.0.x-dev Update solarium/solarium requirement by dependabot[bot] `87429a673 <https://github.com/TYPO3-Solr/ext-solr/commit/87429a673>`_
- Remove OpenSearch profile link by Ingo Fabbri `c9b711756 <https://github.com/TYPO3-Solr/ext-solr/commit/c9b711756>`_
- [BUGFIX] Initialize the localRootLine property before usage by David Lemaitre `3f7d44def <https://github.com/TYPO3-Solr/ext-solr/commit/3f7d44def>`_
- Adjust resource identifier in PageRenderer asset registration by Charlotte `b695abe6c <https://github.com/TYPO3-Solr/ext-solr/commit/b695abe6c>`_


Release 13.0.3
==============

This is a maintenance release for TYPO3 13.4 LTS.

- [TASK] disable dependabot on release-11.6.x branch by Rafael Kähm `(b9771d029) <https://github.com/TYPO3-Solr/ext-solr/commit/b9771d029>`_
- [BUGFIX] Fix mount point garbage collection by Markus Friedrich `(a48f23369) <https://github.com/TYPO3-Solr/ext-solr/commit/a48f23369>`_
- [TASK] Drop workaround for cObj IMAGE by Markus Friedrich `(990046a10) <https://github.com/TYPO3-Solr/ext-solr/commit/990046a10>`_
- [BUGFIX] Avoid PHP warning if related record was not found by Albrecht Köhnlein `(8ce468861) <https://github.com/TYPO3-Solr/ext-solr/commit/8ce468861>`_
- [BUGFIX] Get current pageId by Julian Hofmann `(868d60a72) <https://github.com/TYPO3-Solr/ext-solr/commit/868d60a72>`_

Release 13.0.2
==============

This is a maintenance release for TYPO3 13.4 LTS.

List of all changes:

- [BUGFIX] 404 on auto-suggest with enabled TYPO3 enforceValidation setting by Wolfgang Wagner | wow! solution `901743e85 <https://github.com/TYPO3-Solr/ext-solr/commit/901743e85>`_
- [TASK] 13.0.x-dev Update solarium/solarium requirement by dependabot[bot] `e21bef00f <https://github.com/TYPO3-Solr/ext-solr/commit/e21bef00f>`_
- [BUGFIX] provide some expression matcher variables by Achim Fritz `c810d8986 <https://github.com/TYPO3-Solr/ext-solr/commit/c810d8986>`_
- [TASK] Remove solrmlt by Markus Friedrich `9aba2dff3 <https://github.com/TYPO3-Solr/ext-solr/commit/9aba2dff3>`_
- [TASK] Update version matrix by Markus Friedrich `f1e8cf03a <https://github.com/TYPO3-Solr/ext-solr/commit/f1e8cf03a>`_
- [TASK] Allow Apache Solr 9.8.1 by Markus Friedrich `35a774de0 <https://github.com/TYPO3-Solr/ext-solr/commit/35a774de0>`_
- [DOCS] Add section on how to optimize page index runtimes (#4334) by Philipp Kitzberger `27cbd7612 <https://github.com/TYPO3-Solr/ext-solr/commit/27cbd7612>`_
- [DOCS] Fix typo in ConfigureExtension.rst by Jon Echeveste González `3245c1370 <https://github.com/TYPO3-Solr/ext-solr/commit/3245c1370>`_
- [BUGFIX] PhpUnit IsStringViewHelperTest for typo3fluid/fluid v 4.1.+ by Rafael Kähm `ca41f0fbc <https://github.com/TYPO3-Solr/ext-solr/commit/ca41f0fbc>`_
- [BUGFIX] Update hardcoded legacy css resource filepath by Charlotte `b2bdbbb7c <https://github.com/TYPO3-Solr/ext-solr/commit/b2bdbbb7c>`_
- [TASK] fix integration tests for TYPO3 13.4.10+ by Rafael Kähm `5fe6eb107 <https://github.com/TYPO3-Solr/ext-solr/commit/5fe6eb107>`_
- [FEATURE] Add arm64 and ppc64le platforms to docker-images by Rafael Kähm `253a0a327 <https://github.com/TYPO3-Solr/ext-solr/commit/253a0a327>`_
- [TASK] bump dg/bypass-finals to 1.9+ by Rafael Kähm `33b02aaca <https://github.com/TYPO3-Solr/ext-solr/commit/33b02aaca>`_
- [TASK] Form.html: maxlength for search text input field by Bernd Wilke `650b9439e <https://github.com/TYPO3-Solr/ext-solr/commit/650b9439e>`_
- [TASK] Enable rule trailing_comma_in_multiline by Markus Friedrich `4afd0b5f5 <https://github.com/TYPO3-Solr/ext-solr/commit/4afd0b5f5>`_
- [TASK] Extend RecordUpdatedEvent to indicate creations by Markus Friedrich `c64c4ac81 <https://github.com/TYPO3-Solr/ext-solr/commit/c64c4ac81>`_

Release 13.0.1
==============

List of all changes:

- [TASK] Update version matrix `0ed896675 <https://github.com/TYPO3-Solr/ext-solr/commit/0ed896675>`_
- [BUGFIX] Fix coding standards issues `1f3a281ad <https://github.com/TYPO3-Solr/ext-solr/commit/1f3a281ad>`_
- [BUGFIX] Fix monitoring of mounted pages `ad2548006 <https://github.com/TYPO3-Solr/ext-solr/commit/ad2548006>`_
- [BUGFIX] Add check if generator is valid before traversing it `1e3fe3f70 <https://github.com/TYPO3-Solr/ext-solr/commit/1e3fe3f70>`_
- !!![SECURITY] Update to Apache solr 9.8.0 : CVE-2025-24814 `88918f61d <https://github.com/TYPO3-Solr/ext-solr/commit/88918f61d>`_
- [FIX] docker image tests do not fail if core can not start `2b7b95602 <https://github.com/TYPO3-Solr/ext-solr/commit/2b7b95602>`_
- [DOCS] Actually mention the values of monitoringType `508477f64 <https://github.com/TYPO3-Solr/ext-solr/commit/508477f64>`_
- [BUGFIX] set PageInformation contentFromPid `1c29157cc <https://github.com/TYPO3-Solr/ext-solr/commit/1c29157cc>`_
- [BUGFIX] site has no attribute language `972950af5 <https://github.com/TYPO3-Solr/ext-solr/commit/972950af5>`_
- [BUGFIX] Fix notice exception in ScoreCalculationService `a7b819cbd <https://github.com/TYPO3-Solr/ext-solr/commit/a7b819cbd>`_
- [FEATURE] Add an event for modifying the domain used for a site `2b848a77e <https://github.com/TYPO3-Solr/ext-solr/commit/2b848a77e>`_
- [FIX] Docker tests suite does not contain all logs `a89de2f97 <https://github.com/TYPO3-Solr/ext-solr/commit/a89de2f97>`_
- !!![FIX] Docker execution order issue for as-sudo tweaks `77c3c0f44 <https://github.com/TYPO3-Solr/ext-solr/commit/77c3c0f44>`_
- [BUGFIX] Docker tweaks as-sudo do not preserve the Docker image ENV `eb8546858 <https://github.com/TYPO3-Solr/ext-solr/commit/eb8546858>`_
- [TASK] Use relative path to typo3lib in Apache Solr config `dbbf4c5b4 <https://github.com/TYPO3-Solr/ext-solr/commit/dbbf4c5b4>`_
- [DOCS] Improve Solr core creation via API and other deployment parts `761894713 <https://github.com/TYPO3-Solr/ext-solr/commit/761894713>`_

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

By using our official Docker image from https://hub.docker.com/r/typo3solr/ext-solr,
you want to pull the image v. 13.0.1+ and restart the container with that image, which will run a migrations-script
automatically to secure the configuration in used volume automatically.
No other steps are required.

Alternatively you can wipe the volume and start the container with v. 13.0.1+ image, but that method will wipe the index as well.

See the script `EXT:solr/Docker/SolrServer/docker-entrypoint-initdb.d-as-sudo/fix-CVE-2025-24814.sh`


Other server setups
"""""""""""""""""""

You have 2 possibilities to fix that issue in your Apache Solr Server:


(PREFERRED) Migrate the EXT:solr's Apache Solr configuration
''''''''''''''''''''''''''''''''''''''''''''''''''''''''''''


Refer to https://github.com/TYPO3-Solr/ext-solr/pull/4290/files .

Following 3 files are relevant:

*   Changes in `<Apache-Solr data dir>/configsets/ext_solr_13_0_0/conf/solrconfig.xml`
*   Changes in `<Apache-Solr data dir>/solr.xml`
*   Movement from `<Apache-Solr data dir>/configsets/ext_solr_13_0_0/typo3lib/solr-typo3-plugin-6.0.0.jar`

    *   to `<Apache-Solr data dir>/typo3lib/solr-typo3-plugin-6.0.0.jar`

Steps:

#.  Remove all occurrences of `<lib dir=".*` from `<Apache-Solr data dir>/configsets/ext_solr_13_0_0/conf/solrconfig.xml` file.
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
#.  Move the directory from `<Apache-Solr data dir>/configsets/ext_solr_13_0_0/typo3lib`

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



Release 13.0.0
==============

This is a new major release for TYPO3 13.4 LTS.

New in this release
-------------------

!!! Upgrade to Apache Solr 9.7.0
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

This release requires Apache Solr v 9.7.0+.

Adjust mount point indexing
~~~~~~~~~~~~~~~~~~~~~~~~~~~

Mount point indexing and corresponding tests have been adjusted for TYPO3 13. Mount points are supported in general and the mounted pages will be indexed like standard pages.

But there is a point to consider: Mounted pages from a pagetree without a site configuration cannot be indexed, in fact TYPO3 currently can't mount a page from a page tree without a site configuration and an exeception occurs.
The behavior is intentionally designed this way in TYPO3 core, the background is that it is not possible to specify the languages of the mounted page tree without Site Configuration.

.. note::
   We require at least TYPO3 13.4.2, as this version contains some bugfixes that address problems with the determination of TypoScript and the site configuration of mounted pages.

All Changes
-----------

- [TASK] Prepare main branch for TYPO3 13 by Rafael Kähm `(eaec73806) <https://github.com/TYPO3-Solr/ext-solr/commit/eaec73806>`_
- [TASK] Set Apache Solr configsets to ext_solr_13_0_0 2024.05.13 by Rafael Kähm `(460f919be) <https://github.com/TYPO3-Solr/ext-solr/commit/460f919be>`_
- [BUGFIX] Fix TYPO3 coding standards by Rafael Kähm `(80cfe91dc) <https://github.com/TYPO3-Solr/ext-solr/commit/80cfe91dc>`_
- [TASK] Simple blocker:: come through `typo3 extension:setup` command by Rafael Kähm `(12de6ef21) <https://github.com/TYPO3-Solr/ext-solr/commit/12de6ef21>`_
- [BUGFIX] Set solr configSet to ext_solr_13_0_0 by Thomas Löffler `(c3c317ffe) <https://github.com/TYPO3-Solr/ext-solr/commit/c3c317ffe>`_
- [TASK] Update navigationComponent for page tree in v13 by Thomas Löffler `(64673fd0f) <https://github.com/TYPO3-Solr/ext-solr/commit/64673fd0f>`_
- [TASK] Adapt Unit Tests for TYPO3 v13 by Benni Mack `(c0baedbaa) <https://github.com/TYPO3-Solr/ext-solr/commit/c0baedbaa>`_
- !!![TASK] Change default to not track last searches by Christoph Lehmann `(e1f607a88) <https://github.com/TYPO3-Solr/ext-solr/commit/e1f607a88>`_
- [TASK] Beautify backend modules for v13 by Thomas Löffler `(e51bd8286) <https://github.com/TYPO3-Solr/ext-solr/commit/e51bd8286>`_
- [TASK] Adapt Unit Tests for TYPO3 v13 by Benni Mack `(1c3c35105) <https://github.com/TYPO3-Solr/ext-solr/commit/1c3c35105>`_
- [TASK] Make TSFE resolving work again by Benni Mack `(6e2b3f3b4) <https://github.com/TYPO3-Solr/ext-solr/commit/6e2b3f3b4>`_
- [TASK] Clean up usages of TSFE mocking by Benni Mack `(87630a289) <https://github.com/TYPO3-Solr/ext-solr/commit/87630a289>`_
- [BUGFIX] Fix remaining integration tests by Benni Mack `(f859f0c5b) <https://github.com/TYPO3-Solr/ext-solr/commit/f859f0c5b>`_
- [TASK] fix PhpStan errors for TYPO3 13 by Rafael Kähm `(89d9f0d27) <https://github.com/TYPO3-Solr/ext-solr/commit/89d9f0d27>`_
- [TASK] Disable progress % within Github Actions by Rafael Kähm `(396396979) <https://github.com/TYPO3-Solr/ext-solr/commit/396396979>`_
- [TASK] Run tests daily by Rafael Kähm `(a81626723) <https://github.com/TYPO3-Solr/ext-solr/commit/a81626723>`_
- [FIX] tests for TYPO3 13 @ 2024.07.02 by Rafael Kähm `(20b35ea21) <https://github.com/TYPO3-Solr/ext-solr/commit/20b35ea21>`_
- [FIX] tests for TYPO3 13 @ 2024.07.09 by Rafael Kähm `(c02a3ebbc) <https://github.com/TYPO3-Solr/ext-solr/commit/c02a3ebbc>`_
- [FIX] Integration\SearchTest for TYPO3 13 by Rafael Kähm `(3415e9871) <https://github.com/TYPO3-Solr/ext-solr/commit/3415e9871>`_
- [FIX] require PHP 8.2 for TER version by Rafael Kähm `(3d1092b84) <https://github.com/TYPO3-Solr/ext-solr/commit/3d1092b84>`_
- [FIX] Dependabot not working by Rafael Kähm `(dfcdd98bb) <https://github.com/TYPO3-Solr/ext-solr/commit/dfcdd98bb>`_
- [TASK] Don't store build solrci-image longer as 1 days by Rafael Kähm `(ed561a654) <https://github.com/TYPO3-Solr/ext-solr/commit/ed561a654>`_
- [FIX] GitHub scheduled Actions for daily tests by Rafael Kähm `(556b5d3df) <https://github.com/TYPO3-Solr/ext-solr/commit/556b5d3df>`_
- [FIX] deprecations for Fluid viewHelpers and stack by Rafael Kähm `(216319eed) <https://github.com/TYPO3-Solr/ext-solr/commit/216319eed>`_
- [FIX] Integration\Extbase\PersistenceEventListenerTest errors by Rafael Kähm `(97156bf19) <https://github.com/TYPO3-Solr/ext-solr/commit/97156bf19>`_
- [FIX] Restore BE Modules functionality for TYPO3 13 by Rafael Kähm `(cdd979018) <https://github.com/TYPO3-Solr/ext-solr/commit/cdd979018>`_
- [TASK] migrate to typo3fluid/fluid v4 as required by TYPO3 13 by Rafael Kähm `(064ce710d) <https://github.com/TYPO3-Solr/ext-solr/commit/064ce710d>`_
- [TASK] Remove deprecated queue.[indexConfig].table TypoScript setting by Rafael Kähm `(1a426a6dc) <https://github.com/TYPO3-Solr/ext-solr/commit/1a426a6dc>`_
- [FIX] Translation handling by delegating requered context objects/values by Rafael Kähm `(c3d9db33b) <https://github.com/TYPO3-Solr/ext-solr/commit/c3d9db33b>`_
- [FIX] follow-up for removed queue.[indexConfig].table TypoScript setting by Rafael Kähm `(7fee9368e) <https://github.com/TYPO3-Solr/ext-solr/commit/7fee9368e>`_
- [FIX] wrong Schema version in status checks by Rafael Kähm `(590b34e8d) <https://github.com/TYPO3-Solr/ext-solr/commit/590b34e8d>`_
- [TASK] skip tests for mount-pages temporary #4160 by Rafael Kähm `(32906dccf) <https://github.com/TYPO3-Solr/ext-solr/commit/32906dccf>`_
- [TASK] skip tests for acces restrictions stack temporary #4161 by Rafael Kähm `(f8eeaad03) <https://github.com/TYPO3-Solr/ext-solr/commit/f8eeaad03>`_
- [BUGFIX] PhpStan Variable $parameters in empty() always exists and is not falsy by Rafael Kähm `(2a8596519) <https://github.com/TYPO3-Solr/ext-solr/commit/2a8596519>`_
- [FIX] Tests for TYPO3 dev-main @2024.09.23 by Rafael Kähm `(ff7e038f7) <https://github.com/TYPO3-Solr/ext-solr/commit/ff7e038f7>`_
- [BUGFIX] Failed to resolve module specifier '@apache-solr-for-typo3/solr//FormModal.js' by Rafael Kähm `(3c86a707f) <https://github.com/TYPO3-Solr/ext-solr/commit/3c86a707f>`_
- [BUGFIX] `@typo3/backend/tree/page-tree-element` does not work in BE-Modules by Rafael Kähm `(111f68404) <https://github.com/TYPO3-Solr/ext-solr/commit/111f68404>`_
- [FIX] access restrictions stack for TYPO3 13 by Rafael Kähm `(dc7162b25) <https://github.com/TYPO3-Solr/ext-solr/commit/dc7162b25>`_
- [FIX] `#[Group('frontend')]` attribute has comment in SearchControllerTest by Rafael Kähm `(0514886b4) <https://github.com/TYPO3-Solr/ext-solr/commit/0514886b4>`_
- [TASK] Adjust configuration check and fallbacks in MultiValue CO by Markus Friedrich `(ea883ce33) <https://github.com/TYPO3-Solr/ext-solr/commit/ea883ce33>`_
- [TASK] Adapt simulated environment for TYPO3 13 by Markus Friedrich `(fb9fdd8c8) <https://github.com/TYPO3-Solr/ext-solr/commit/fb9fdd8c8>`_
- Update TxSolrSearch.rst by Florian Seirer `(f8d330082) <https://github.com/TYPO3-Solr/ext-solr/commit/f8d330082>`_
- [TASK] Update dependencies by Rafael Kähm `(01e5387c0) <https://github.com/TYPO3-Solr/ext-solr/commit/01e5387c0>`_
- [TASK] fix CS issues for newest typo3/coding-standards by Rafael Kähm `(8c1e28850) <https://github.com/TYPO3-Solr/ext-solr/commit/8c1e28850>`_
- !!![TASK] Upgrade to Apache Solr 9.7.0 by Markus Friedrich `(323b1f0c2) <https://github.com/TYPO3-Solr/ext-solr/commit/323b1f0c2>`_
- [RELEASE] 13.0.0-alpha-1 by Markus Friedrich `(3bd453d09) <https://github.com/TYPO3-Solr/ext-solr/commit/3bd453d09>`_
- [FIX] allow tags/releases from main branch by Rafael Kähm `(26e38f8b7) <https://github.com/TYPO3-Solr/ext-solr/commit/26e38f8b7>`_
- [TASK] migrate plugin subtype "list_type" by Rafael Kähm `(0c0f2b953) <https://github.com/TYPO3-Solr/ext-solr/commit/0c0f2b953>`_
- [TASK] Upgrade typo3/testing-framework to dev-main 2024.10.15 by Rafael Kähm `(a4596d49e) <https://github.com/TYPO3-Solr/ext-solr/commit/a4596d49e>`_
- [TASK] Use TYPO3 13.4+ and 13.4.x-dev after TYPO3 13 LTS release by Rafael Kähm `(0fd63e172) <https://github.com/TYPO3-Solr/ext-solr/commit/0fd63e172>`_
- [TASK] Remove JSONP callback in suggest by Benni Mack `(094b4e5b2) <https://github.com/TYPO3-Solr/ext-solr/commit/094b4e5b2>`_
- [FEATURE] Introduce method to unset the query string (#4136) by Ayke Halder `(b0ddab00e) <https://github.com/TYPO3-Solr/ext-solr/commit/b0ddab00e>`_
- Update ExtensionSettings.rst by Jon Echeveste González `(d79c92c9d) <https://github.com/TYPO3-Solr/ext-solr/commit/d79c92c9d>`_
- [FEATURE] Make Node->depth actually initialized and usable by snk-spo `(b530a2f03) <https://github.com/TYPO3-Solr/ext-solr/commit/b530a2f03>`_
- [TASK] Update version matrix by Markus Friedrich `(b6bfad8f1) <https://github.com/TYPO3-Solr/ext-solr/commit/b6bfad8f1>`_
- [TASK] 13.0.x-dev Update solarium/solarium requirement by dependabot[bot] `(64e978646) <https://github.com/TYPO3-Solr/ext-solr/commit/64e978646>`_
- [TASK] improve exception handling by Rafael Kähm `(8f1597b4d) <https://github.com/TYPO3-Solr/ext-solr/commit/8f1597b4d>`_
- [FIX] Garbage collector does not get configuration by Rafael Kähm `(f73de9da2) <https://github.com/TYPO3-Solr/ext-solr/commit/f73de9da2>`_
- [FIX] CS in Configuration/Backend/Modules.php by Rafael Kähm `(08f717129) <https://github.com/TYPO3-Solr/ext-solr/commit/08f717129>`_
- [FIX] deprecations in Dockerfile by Rafael Kähm `(af1e8cdcd) <https://github.com/TYPO3-Solr/ext-solr/commit/af1e8cdcd>`_
- [BUGFIX] Ensure index document is deleted by Markus Friedrich `(10c0fde3c) <https://github.com/TYPO3-Solr/ext-solr/commit/10c0fde3c>`_
- [DOCs] for release 12.0.4 by Rafael Kähm `(7b61833ad) <https://github.com/TYPO3-Solr/ext-solr/commit/7b61833ad>`_
- [DOCs] Update EXT:solr 12.0.x line in version matrix by Rafael Kähm `(ac1ff3663) <https://github.com/TYPO3-Solr/ext-solr/commit/ac1ff3663>`_
- [FIX] phpstan: Method UrlHelper::withQueryParameter() has parameter $value with no type specified by Rafael Kähm `(588564f27) <https://github.com/TYPO3-Solr/ext-solr/commit/588564f27>`_
- [TASK] Remove Scrutinizer integrations on release-12.0.x by Rafael Kähm `(c2558c1d3) <https://github.com/TYPO3-Solr/ext-solr/commit/c2558c1d3>`_
- [FIX] Re-added template variables for SearchFormViewHelper by thomashohn `(f7ad16ae4) <https://github.com/TYPO3-Solr/ext-solr/commit/f7ad16ae4>`_
- [DOCs] for release 12.0.5 by Rafael Kähm `(ec97b6fd1) <https://github.com/TYPO3-Solr/ext-solr/commit/ec97b6fd1>`_
- [TASK] Remove Implicitly nullable parameter declarations deprecated by Thomas Hohn `(207a0e5fa) <https://github.com/TYPO3-Solr/ext-solr/commit/207a0e5fa>`_
- Update composer requirement by Thomas Hohn `(43f3baa94) <https://github.com/TYPO3-Solr/ext-solr/commit/43f3baa94>`_
- [TASK] CS change to multiline parameters with comma on last by Rafael Kähm `(9aa403a65) <https://github.com/TYPO3-Solr/ext-solr/commit/9aa403a65>`_
- [TASK] Clean and improve ConnectionManagerTest by Markus Friedrich `(edf482457) <https://github.com/TYPO3-Solr/ext-solr/commit/edf482457>`_
- [TASK] Adjust mount point indexing by Markus Friedrich `(bf446c032) <https://github.com/TYPO3-Solr/ext-solr/commit/bf446c032>`_
- [BUGFIX] Fix record monitoring if site is missing by Markus Friedrich `(0dfd4b454) <https://github.com/TYPO3-Solr/ext-solr/commit/0dfd4b454>`_
- [TASK] Evaluate all entries in Services.yaml regarding to `shared` setting by Rafael Kähm `(f8083a616) <https://github.com/TYPO3-Solr/ext-solr/commit/f8083a616>`_
- [TASK] Add int cast for sys_language_uid by Guido Schmechel `(de7d7efa7) <https://github.com/TYPO3-Solr/ext-solr/commit/de7d7efa7>`_
- [TASK] Add int cast for sys_language_uid by Guido Schmechel `(5d659dd3a) <https://github.com/TYPO3-Solr/ext-solr/commit/5d659dd3a>`_
- [DOCS] Switch documentation rendering to PHP-based rendering by Rafael Kähm `(4f7b9a73e) <https://github.com/TYPO3-Solr/ext-solr/commit/4f7b9a73e>`_
- [DOCS] workaround for version matrix by Rafael Kähm `(bc5bf0b6f) <https://github.com/TYPO3-Solr/ext-solr/commit/bc5bf0b6f>`_
- [FEATURE] Add timeframe filter to statistics module by Bastien Lutz `(0fc8d7cbd) <https://github.com/TYPO3-Solr/ext-solr/commit/0fc8d7cbd>`_
- [BUGFIX] Respect foreignLabel in related items from mm table by Till Hörner `(f5271b049) <https://github.com/TYPO3-Solr/ext-solr/commit/f5271b049>`_
- [BUGFIX] Make getHasChildNodeSelected recursive by Tobias Wojtylak `(a128c3018) <https://github.com/TYPO3-Solr/ext-solr/commit/a128c3018>`_
- [BUGFIX] Add StartTimeRestriction to ConfigurationAwareRecordService by Amir Arends `(27f36af68) <https://github.com/TYPO3-Solr/ext-solr/commit/27f36af68>`_
- [FEATURE] Use PHP generator to prevent processing of all available site by Stefan Frömken `(7fec14dc4) <https://github.com/TYPO3-Solr/ext-solr/commit/7fec14dc4>`_
- [FIX] Indexing fails with SOLR_* cObj in TypoScript by Rafael Kähm `(bcb252197) <https://github.com/TYPO3-Solr/ext-solr/commit/bcb252197>`_
- [FIX] missing TypoScript configuration on RecordMonitor stack by Rafael Kähm `(31199d2a1) <https://github.com/TYPO3-Solr/ext-solr/commit/31199d2a1>`_


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

*  Albrecht Köhnlein
*  Amir Arends
*  Ayke Halder
*  Bastien Lutz
*  Benni Mack
*  Bernd Wilke
*  Christoph Lehmann
*  @chrrynobaka
*  @derMatze82
*  Florian Seirer
*  Guido Schmechel
*  Hendrik vom Lehn
*  Jon Echeveste González
*  Lars Tode
*  Markus Friedrich
*  Rafael Kähm
*  @snk-spo
*  Stefan Frömken
*  Thomas Hohn
*  Thomas Löffler
*  Till Hörner
*  Tobias Wojtylak
*  Torben Hansen
*  Wolfgang Wagner

Also a big thank you to our partners who have already concluded one of our development participation packages such
as Apache Solr EB for TYPO3 13 LTS (Feature):

*   +Pluswerk AG
*   .hausformat
*   711media websolutions GmbH
*   Amt der Oö Landesregierung
*   Autorité des marchés financiers
*   Berlin-Brandenburgische Akademie der Wissenschaften
*   Bestellung EB13 SOS Software GmbH für Telekom
*   CS2 AG
*   F7 Media GmbH
*   Fachhochschule Erfurt
*   Getdesigned GmbH
*   Groupe Toumoro inc
*   KONVERTO AG
*   Kassenärztliche Vereinigung Rheinland-Pfalz
*   Kreis Euskirchen
*   LOUIS INTERNET GmbH
*   Leuchtfeuer Digital Marketing GmbH
*   LfdA - Labor für digitale Angelegenheiten GmbH
*   MOSAIQ GmbH
*   Marketing Factory Digital GmbH
*   ProPotsdam GmbH
*   SITE'NGO
*   Snowflake Productions GmbH
*   Stämpfli AG
*   THE BRETTINGHAMS GmbH
*   b13 GmbH
*   clickstorm GmbH
*   cron IT GmbH
*   graphodata GmbH
*   i-kiu motion
*   in2code GmbH
*   internezzo ag
*   jweiland.net e.K.
*   mehrwert intermediale kommunikation GmbH
*   network.publishing Möller-Westbunk GmbH
*   plan2net GmbH
*   queo GmbH
*   visol digitale Dienstleistungen GmbH
*   werkraum Digitalmanufaktur GmbH

How to Get Involved
===================

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports and feature requests on `GitHub <https://github.com/TYPO3-Solr/ext-solr>`__
* Ask or help or answer questions in our `Slack channel <https://typo3.slack.com/messages/ext-solr/>`__
* Provide patches through Pull Request or review and comment on existing `Pull Requests <https://github.com/TYPO3-Solr/ext-solr/pulls>`__
* Go to `www.typo3-solr.com <https://www.typo3-solr.com>`__ or call `dkd <http://www.dkd.de>`__ to sponsor the ongoing development of Apache Solr for TYPO3

Support us by becoming an EB partner:

https://shop.dkd.de/Produkte/Apache-Solr-fuer-TYPO3/

or call:

+49 (0)69 - 2475218 0
