.. _releases-14-0:

=============
Releases 14.0
=============

Release 14.0.1
==============

This release solves the troubles within TER/classic-mode TYPO3 instances and is only relevant for classic-mode installations.

All Changes
-----------

*   [FEATURE] simplify release process by @dkd-kaehm in `#4769 <https://github.com/TYPO3-Solr/ext-solr/pull/4769>`_
*   [BUGFIX] Make EXT:solr compatible with non-Composer/classic TYPO3 instances by @dkd-kaehm in `#4768 <https://github.com/TYPO3-Solr/ext-solr/pull/4768>`_


Release 14.0.0
==============

This is a new major release for TYPO3 14 LTS.

Highlights
----------

Everything announced during the 14.0.0 pre-release cycle — 14.0.0-alpha1, -beta1,
-beta2, -beta3 and -RC1 — collected for the stable release, together with the changes
that landed after the release candidate. The sections further down describe the
individual changes in detail.

Security
~~~~~~~~

*   CVE-2026-56092 to CVE-2026-56096, reported through the TYPO3 Security Team. Field
    selectors in ``tx_solr[q]`` are whitelisted and the query syntax is escaped,
    multi-value cObjs use JSON transport instead of object deserialization,
    ``tx_solr[additionalFilters]`` can no longer preempt the siteHash or access filter,
    the detail action enforces site and access restrictions, and the
    ``fe_group``/``extendToSubpages``-forging listener is removed. Installations on
    14.0.0-RC1 or earlier should update.
    (`#4751 <https://github.com/TYPO3-Solr/ext-solr/pull/4751>`__,
    `#4763 <https://github.com/TYPO3-Solr/ext-solr/pull/4763>`__ / @dkd-kaehm)

TYPO3 14 and the platform
~~~~~~~~~~~~~~~~~~~~~~~~~

*   !!! Full TYPO3 14 LTS compatibility, on stable TYPO3 14.3.x.
    (`#4620 <https://github.com/TYPO3-Solr/ext-solr/pull/4620>`__ / @dkd-kaehm)
*   !!! Apache Solr 10 / Lucene 10 is now required. The configset and the
    managed-resources API were adapted for Jetty 12, deprecated trie-based dynamic
    fields were removed, and ``ExtractingRequestHandler`` was dropped in favour of
    EXT:tika 14+.
    (`#4562 <https://github.com/TYPO3-Solr/ext-solr/pull/4562>`__ / @dkd-dobberkau)
*   !!! solarium/solarium 7.0.0 removes :php:`AbstractQueryBuilder::removeOperator()`
    and :php:`removeAlternativeQuery()` without alternatives.
    (`#4713 <https://github.com/TYPO3-Solr/ext-solr/pull/4713>`__ / @dependabot)
*   All language files were migrated to XLIFF 2.0, and event listeners moved to the
    :php:`#[AsEventListener]` PHP attribute.
    (`#4575 <https://github.com/TYPO3-Solr/ext-solr/pull/4575>`__,
    `#4588 <https://github.com/TYPO3-Solr/ext-solr/pull/4588>`__ / @sfroemkenjw)
*   The scheduler tasks implement :php:`getTaskParameters()` /
    :php:`setTaskParameters()`, so TYPO3 core's
    :php:`SchedulerDatabaseStorageMigration` can migrate them. Re-run that migration if
    your EXT:solr tasks were not migrated before this release.
    (`#4715 <https://github.com/TYPO3-Solr/ext-solr/pull/4715>`__ / @dkd-kaehm)
*   EXT:install became an optional dependency.
    (`#4676 <https://github.com/TYPO3-Solr/ext-solr/pull/4676>`__ / @wazum)
*   Static analysis moved to PHPStan 2, which also uncovered a :php:`str_replace()`
    search-array type mismatch in :php:`RoutingUtility::buildHash()`.
    (`#4710 <https://github.com/TYPO3-Solr/ext-solr/pull/4710>`__ / @dkd-kaehm)

Indexing
~~~~~~~~

*   !!! Unified sub-request indexing pipeline. Indexing no longer makes HTTP
    round-trips to itself, which makes it significantly faster. (@dkd-kaehm)
*   !!! The ``indexer`` Index Queue setting and the Index Queue Indexer are retired.
    Indexing runs through :php:`IndexingService` and the PSR-14 indexing events, which
    is where integrators who replaced the indexer now hook in.
    (`#4758 <https://github.com/TYPO3-Solr/ext-solr/pull/4758>`__,
    `#4760 <https://github.com/TYPO3-Solr/ext-solr/pull/4760>`__ / @dkd-kaehm)
*   !!! :php:`RecordInsertedEvent` was introduced and the ``isNewRecord`` flag was
    dropped from :php:`RecordUpdatedEvent`. (@dkd-friedrich)
*   !!! A failed Index Queue item now records why it failed, and Index Queue
    initialization stops at nested site roots instead of crossing into them.
    (`#4759 <https://github.com/TYPO3-Solr/ext-solr/pull/4759>`__ / @dkd-kaehm)
*   The new :php:`BeforeIndexingSubRequestIsPreparedEvent` lets listeners reset state
    that must not leak between indexing sub-requests.
    (`#4738 <https://github.com/TYPO3-Solr/ext-solr/pull/4738>`__ / @dkd-kaehm)
*   The site hash is finalized by site identifier: dedicated ``domain`` and
    ``typo3Context`` schema fields replace the ad-hoc ``_stringS`` fields that
    :php:`Builder` used to write, and the recommended schema/solrconfig version stamp
    was bumped.
    (`#4719 <https://github.com/TYPO3-Solr/ext-solr/pull/4719>`__ / @dkd-kaehm)
*   No ``c:0`` variant and no content leakage on ``fe_group``-restricted pages.
    Changing permissions cascades through ``extendToSubpages``, so outdated access
    variants are reindexed and cleaned up. (@dkd-kaehm)
*   State no longer leaks between indexing sub-requests. Fixed for the language
    Context aspect (`#4717 <https://github.com/TYPO3-Solr/ext-solr/pull/4717>`__ /
    @BastiLu), the page title
    (`#4701 <https://github.com/TYPO3-Solr/ext-solr/pull/4701>`__ / @amirarends),
    Context aspects in general
    (`#4733 <https://github.com/TYPO3-Solr/ext-solr/pull/4733>`__ / @hdj-typoconsult),
    the page cache (@amirarends) and the backend web context
    (`#4628 <https://github.com/TYPO3-Solr/ext-solr/pull/4628>`__ / @dkd-kaehm, with
    patterns from @dmitryd). The last one fixes the Scheduler-module crash and CLI
    multi-task chaining when indexing runs in a backend web context.
*   Site processing uses a PHP generator, which lowers memory use. (@sfroemkenjw)
*   The PID where a request failed is logged.
    (`#4712 <https://github.com/TYPO3-Solr/ext-solr/pull/4712>`__ / @guelzow)
*   No PHP warning when the access rootline is built for Index Queue pages that were
    not collected.
    (`#4728 <https://github.com/TYPO3-Solr/ext-solr/pull/4728>`__ / @konradmichalik)
*   :php:`TypoScriptConfiguration` is no longer resolved for a page deleted in a
    workspace.
    (`#4603 <https://github.com/TYPO3-Solr/ext-solr/pull/4603>`__ / @amirarends)
*   The obsolete ``addRootLineFields`` setting was removed.
    (`#4735 <https://github.com/TYPO3-Solr/ext-solr/pull/4735>`__ / @tillhoerner)

Search and frontend
~~~~~~~~~~~~~~~~~~~

*   !!! jQuery is gone from the frontend JavaScript. The search, suggest, facet and
    range controllers were rewritten in vanilla JavaScript, and ``autoComplete.js``
    drives the autosuggest.
    (`#4619 <https://github.com/TYPO3-Solr/ext-solr/pull/4619>`__ / @dkd-lehnebach)
*   Site sets are registered for all TypoScript templates.
    (`#4622 <https://github.com/TYPO3-Solr/ext-solr/pull/4622>`__ / @dmitryd)
*   Spellchecking keeps correctly-spelled terms when offering corrections. The "did you
    mean" link and the auto-correction used to drop every correctly-spelled term from a
    multi-word query.
    (`#4671 <https://github.com/TYPO3-Solr/ext-solr/pull/4671>`__ / @dkd-kaehm)
*   Suggest fixes: the form submission can be prevented because
    :code:`form.requestSubmit()` replaced :code:`form.submit()`
    (`#4657 <https://github.com/TYPO3-Solr/ext-solr/pull/4657>`__ / @danilovq), the
    dropdown positions itself correctly in mobile and offcanvas layouts
    (`#4652 <https://github.com/TYPO3-Solr/ext-solr/pull/4652>`__ / @dmitryd), the
    suggestion query respects a configured ``routeEnhancer``
    (`#4644 <https://github.com/TYPO3-Solr/ext-solr/pull/4644>`__ / @dkd-lehnebach),
    and several search forms on one page initialize correctly
    (`#4747 <https://github.com/TYPO3-Solr/ext-solr/pull/4747>`__ / @dkd-lehnebach).
*   Facet URL encoding no longer mismatches on spaces when
    ``urlParameterStyle=assoc`` is used.
    (`#4610 <https://github.com/TYPO3-Solr/ext-solr/pull/4610>`__ / @dkd-hauser)
*   ``subTitle`` and ``navTitle`` use the correct field-name casing in the TypoScript
    ``queryFields``.
    (`#4618 <https://github.com/TYPO3-Solr/ext-solr/pull/4618>`__ / @amirarends)
*   Long Solr GET requests are converted to POST on the PSR-14 dispatcher, and the
    ``PostBigRequest`` listener became opt-in through the extension configuration.
    (`#4631 <https://github.com/TYPO3-Solr/ext-solr/pull/4631>`__ / @wazum)
*   ASCII folding is applied before stemming in all language schemas.
    (`#4741 <https://github.com/TYPO3-Solr/ext-solr/pull/4741>`__ / @tgaertner)
*   The backend module icons were replaced with the TYPO3 14 style.
    (`#4611 <https://github.com/TYPO3-Solr/ext-solr/pull/4611>`__ / @konradmichalik)
*   No undefined array key exception for ``flexParentDatabaseRow``, which is not always
    set — for example when the record history of a just-edited search flexform element
    is opened.
    (`#4698 <https://github.com/TYPO3-Solr/ext-solr/pull/4698>`__ / @BastiLu)
*   A record is no longer loaded completely when
    :php:`SettingsPreviewOnPluginsEventListener` only needs part of it, and it no longer
    warns about an undefined array key.
    (`#4691 <https://github.com/TYPO3-Solr/ext-solr/pull/4691>`__ / @un3us,
    `#4686 <https://github.com/TYPO3-Solr/ext-solr/pull/4686>`__ / @mschwemer)

Removed API
~~~~~~~~~~~

*   !!! :php:`DataUpdateHandler::removeFromIndexAndQueueWhenItemInQueue()`,
    :php:`PageIndexer::isPageIndexable()`,
    :php:`QueueInitializationServiceAwareInterface` and the related Queue API are
    removed. (@dkd-friedrich)
*   !!! The legacy PageIndexer system is removed and replaced by
    ``IndexingInstructions``. (@dkd-kaehm)
*   !!! The site hash strategy flag is removed.
    (`#4546 <https://github.com/TYPO3-Solr/ext-solr/pull/4546>`__ / @bmack)
*   !!! The trailing space is removed from the ``searchResultClassName`` and
    ``searchResultSetClassName`` configuration keys.
    (`#4226 <https://github.com/TYPO3-Solr/ext-solr/pull/4226>`__ / @beardcoder)
*   !!! :php:`Highlighting::getUseFastVectorHighlighter()` is removed together with the
    FastVector Highlighter selection rule. (@dkd-kaehm)

Housekeeping during the pre-releases
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

*   The ``guzzlehttp/psr7 < 2.10.0`` pin that 14.0.0-beta2 had to introduce is gone
    again as of 14.0.0-beta3. ``guzzlehttp/psr7`` 2.10.0 made
    :php:`Utils::modifyRequest()` mutate the original :php:`RequestInterface`, which
    together with Guzzle's :php:`PrepareBodyMiddleware` passing ``Content-Length`` as an
    :php:`int` broke every Solr write request through Solarium's :php:`Psr18Adapter` on
    spec-compliant PSR-7 implementations such as :php:`TYPO3\CMS\Core\Http\Message`.
    ``guzzlehttp/guzzle`` 7.10.2 fixed it upstream, so nothing needs to be pinned when
    upgrading from 13.x.
    (`#4660 <https://github.com/TYPO3-Solr/ext-solr/issues/4660>`__ / @dkd-kaehm)
*   Extensive ViewHelper and dependency-injection refactoring across
    :php:`SearchFormViewHelper`, the facet ViewHelpers (now sharing a trait),
    Classification handling, :php:`FrequentSearchesService` and
    :php:`SettingsPreviewOnPlugins`. (@sfroemkenjw)
*   The dependency on EXT:fluid_styled_content was dropped.
    (`#4729 <https://github.com/TYPO3-Solr/ext-solr/pull/4729>`__ / @dmitryd)
*   Documentation fixes: the case-sensitive ``X-Tx-Solr-Iq`` header in
    :file:`BestPractice.rst`
    (`#4656 <https://github.com/TYPO3-Solr/ext-solr/pull/4656>`__ / @hnadler), clearing
    documents only for the current site in :file:`Indexing.rst`
    (`#4672 <https://github.com/TYPO3-Solr/ext-solr/pull/4672>`__ / @pi-phi), the
    requirements for searchable and sortable fields
    (`#4726 <https://github.com/TYPO3-Solr/ext-solr/pull/4726>`__ / @kitzberger) and
    updated links to the Apache Solr Reference Guide
    (`#4736 <https://github.com/TYPO3-Solr/ext-solr/pull/4736>`__ / @tillhoerner).

New in this release
-------------------

TYPO3 14 LTS Compatibility
~~~~~~~~~~~~~~~~~~~~~~~~~~

EXT:solr has been fully adapted for TYPO3 14 LTS, including Fluid v5 ViewHelper
compatibility, TCA changes, deprecation removals, and testing framework updates.

TYPO3 Site Sets
~~~~~~~~~~~~~~~

EXT:solr now provides TYPO3 site sets for the base configuration, optional
frontend assets, OpenSearch and the shipped example configurations. For TYPO3
14 projects that do not use TypoScript template records, site set dependencies
are the recommended way to include EXT:solr configuration.

See :ref:`configuration-site-sets` for the full list of available site sets.

XLIFF 2.0 Migration
~~~~~~~~~~~~~~~~~~~

All language files have been migrated from XLIFF 1.2 to XLIFF 2.0 format.

Parallel Solr Worker Cores for Integration Tests
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Integration tests now use parallel Solr worker cores via paratest, significantly
improving test execution speed.

Scheduler Tasks Support TYPO3 14 Migration to New Task Storage
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

EXT:solr's scheduler tasks (``EventQueueWorkerTask``, ``IndexQueueWorkerTask``, ``OptimizeIndexTask``, ``ReIndexTask``)
now implement :php:`getTaskParameters()` / :php:`setTaskParameters()`,
so TYPO3 core's :php:`\TYPO3\CMS\Scheduler\Migration\SchedulerDatabaseStorageMigration`
can migrate them to the new task storage without failing.

.. important::
   If your EXT:solr scheduler tasks were not yet migrated before updating to EXT:solr 14.0.0-RC1,
   mark ``schedulerDatabaseStorageMigration`` as undone in the Upgrade module and run it again.

See `#4525 <https://github.com/TYPO3-Solr/ext-solr/issues/4525>`_.

Event Listener Migration to PHP Attributes
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Event listeners have been refactored to use the ``#[AsEventListener]`` PHP
attribute instead of ``Services.yaml`` tag registration, following TYPO3 14
best practices.

Unified Sub-Request Indexing Pipeline
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The page indexing system has been completely rewritten. The legacy HTTP-based
``PageIndexer`` (which made real HTTP round-trips via ``X-Tx-Solr-Iq`` headers)
has been replaced by a unified in-process sub-request pipeline using TYPO3's
``Application::handle()``.

Key changes:

*  ``IndexingService`` orchestrates all indexing via ``Application::handle()``
   sub-requests — no more HTTP round-trips
*  ``SolrIndexingMiddleware`` handles page rendering, document creation, and
   Solr submission within the standard TYPO3 middleware stack
*  ``UserGroupDetectionMiddleware`` + ``UserGroupDetector`` detect frontend
   user groups during page rendering without Singleton state or TCA manipulation
*  ``CliEnvironment`` and ``forcedWebRoot`` scheduler option removed — sub-requests
   use ``chdir(Environment::getPublicPath())`` to ensure correct working directory
*  12.7% faster indexing (493.9s → 431.3s for 59 pages) with ~3,200 lines removed

See `#4559 <https://github.com/TYPO3-Solr/ext-solr/pull/4559>`_ and
`#4598 <https://github.com/TYPO3-Solr/ext-solr/issues/4598>`_ for details.

Bugfix: No ``c:0`` Variant on fe_group-restricted Pages
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Two interrelated bugs in the new sub-request indexing pipeline caused
access-protected pages to be indexed with incorrect Solr documents.

**Bug 1 — Spurious ``c:0`` variant from global template content:**
During the ``findUserGroups`` sub-request, TYPO3 renders the full page
including global template content (footer, navigation) from other pages.
Content elements without ``fe_group`` restriction in these template areas
caused ``UserGroupDetector`` to collect group ``0``, which then triggered
a ``c:0`` Solr variant even for pages where all actual page content is
access-restricted. With ``fe_group=1`` on the page, the ``c:0`` variant
was never meaningfully accessible (its access rootline already required
group 1), but it polluted the index with empty or incorrect documents.

Fix: When ``pageUserGroup > 0``, group ``0`` is removed from the detected
access groups. The page's own group is added as a fallback so that users
holding only the page group can still find the page in search results.

**Bug 2 — Protected content leaking into the ``c:0`` variant:**
When indexing the ``c:0`` variant (anonymous rendering), ``FrontendGroupsModifier``
unconditionally added ``pageUserGroup`` to the faked frontend groups. This
granted access to ``fe_group``-restricted content elements during the
anonymous rendering sub-request, causing protected content to appear in
the public Solr document — a potential content disclosure issue.

Fix: ``pageUserGroup`` is now only added to the faked groups when
``userGroup > 0`` (i.e. not during the anonymous ``c:0`` rendering).

As a result, pages with ``fe_group`` restriction no longer produce a
``c:0`` Solr document. Instead, the page's own group (e.g. ``c:1``) is
used as the base content access variant.

Bugfix: BE Web Context Preservation During Indexing
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

When the ``IndexQueueWorker`` scheduler task is dispatched from the BE
web context (Scheduler module) or chained on the CLI via
``scheduler:run``, the frontend sub-request executed during indexing
replaced global state that the caller relies on:

*  ``$GLOBALS['BE_USER']`` and ``$GLOBALS['LANG']`` are overridden by
   the frontend ``BackendUserAuthenticator`` middleware.
*  ``$GLOBALS['TYPO3_REQUEST']`` is reassigned by the frontend
   ``RequestHandler``.
*  ``AssetCollector`` and ``PageRenderer`` singleton state is mutated
   by the frontend rendering chain.

The Scheduler module then crashed when rendering its task-list view
after the task (``TypeError`` on ``ModuleTemplate::getBackendUser()``,
or broken BE styles), and ``scheduler:run`` chaining multiple tasks
crashed in ``DataHandler`` because the next task expected a real
``BackendUserAuthentication`` instance.

Fix: ``IndexingService::executeSubRequest()`` now snapshots all of
this state before the sub-request and restores it in the ``finally``
block — for ``$GLOBALS`` keys preserving the "absent vs. ``null``"
distinction. The pattern is inspired by the testing-framework's
``FrameworkState`` class, scoped to the production state we observe
getting tainted.

See `#4628 <https://github.com/TYPO3-Solr/ext-solr/issues/4628>`_
and `#4647 <https://github.com/TYPO3-Solr/ext-solr/pull/4647>`_.

Bugfix: Spellchecking Dropped Correctly-Spelled Terms From Corrections
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The "did you mean" link and the spellchecker auto-correction used to drop every correctly-spelled term from a multi-word query,
leaving only the suggested replacement word — so a search for "hello formuller" would offer "formular" instead of "hello formular".

Fix: The primary path now uses Solr's ``spellcheck.collations`` field, which already contains the full corrected query.
When Solr discards the collation (e.g. because ``spellcheck.maxCollationTries=1`` filters out collations that match no documents),
a per-suggestion ``fullQuery`` falls back to reconstructing the query from the suggestion's ``startOffset``/``endOffset``
against ``responseHeader.params.q``, with a word-boundary ``preg_replace`` as a last-resort fallback.
This also fixes a latent bug where the misspelled term was captured from the array index instead of the actual string in the alternating flat NamedList.

See `#4659 <https://github.com/TYPO3-Solr/ext-solr/issues/4659>`_.


Security: CVE-2026-56096 — FieldExistsQuery HTTP 500 Oracle Closed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

A stand-alone ``field:*`` query was forwarded through the FastVector Highlighter (FVH).
Lucene rewrote it to a ``FieldExistsQuery``, which FVH could not handle, and the request failed with HTTP 500 —
turning the response status into a field-existence oracle on the indexed schema.

Fix: EXT:solr now requests the Unified Highlighter unconditionally, with ``hl.offsetSource=ANALYSIS``
so that highlighted fields do not need ``storeOffsetsWithPositions``.
``hl.bs.type=WORD`` and ``hl.fragsizeIsMinimum=false`` are kept,
so ``fragmentSize`` retains its soft upper bound.
``hl.defaultSummary=true`` replaces the ``hl.alternateField`` teaser fallback,
which the Unified Highlighter ignores.

The shipped ``ext_solr_14_0_0`` configset sets the same defaults on the ``/select`` and ``/browse`` request handlers.

..  attention::
    **Docker users:** the configset is copied into the Solr data volume on first container start,
    so pulling a new EXT:solr version does not refresh an existing volume.
    If you already run a Solr container seeded from a pre-release ``ext_solr_14_0_0`` configset,
    its ``/select`` and ``/browse`` handlers still default to the legacy highlighter
    and stay vulnerable when queried directly, bypassing EXT:solr.
    Remove the Solr data volume and restart the container so the patched configset is seeded,
    then re-index — dropping the volume also drops the index.

    EXT:solr itself enforces the Unified Highlighter in PHP,
    so this configset alignment is a defence-in-depth measure for clients that query Solr directly.

No stable 14.0.0 release shipped the vulnerable configset,
so the ``fix-SST-235567-2026050810000025-highlighter-defaults.sh`` migration script
that accompanies the 13.1.4 release is not part of 14.0.0.

Also released for TYPO3 13 LTS as EXT:solr 13.1.4.

SST ticket: #2026050810000025, dkd: #235567


Security: CVE-2026-56096 — Field Selectors and Query Syntax Restricted
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``tx_solr[q]`` reached the edismax parser unescaped and without a field whitelist,
so a visitor could aim Lucene selector, range and grouping syntax at any field of the index
and read back values that the search interface itself never exposes.

The fix adds two layers, each governed by a new TypoScript setting:

*   ``plugin.tx_solr.search.query.userFields`` sets the edismax ``uf`` parameter,
    derived from ``query.queryFields`` by default.
    A selector aimed at a field outside that whitelist is parsed as a literal term and misses.
    Sites that rely on selectors against non-``qf`` fields extend the whitelist
    with a scalar override or with the ``add`` / ``remove`` sub-keys.
*   ``plugin.tx_solr.search.query.allowSolrOperatorSyntax`` escapes the user input itself.
    Selector, range and grouping characters (``: [ ] ( ) { } ^ " ~ \ /``)
    are escaped no matter how the setting is configured.
    The default ``1`` lets the documented ``+ - && || ! * ?`` operator UX pass through,
    ``0`` escapes the SolrJ specials ``| & ;`` on top of that.

The pure vector search path is unaffected, because it never carries a user query term.

See :ref:`configuration.reference.solrsearch` for the full reference of both settings.

Also released for TYPO3 13 LTS as EXT:solr 13.1.4.

SST ticket: #2026050810000025, dkd: #235567


Security: CVE-2026-56095 — No Object Injection Through Indexed Record Fields
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The indexer ran ``unserialize()`` over whatever a multi-value ``cObj`` returned for a Solr field.
Anyone able to influence an indexed record field could put serialized bytes there and have the
indexing run reconstruct arbitrary PHP objects, which turned the indexer into an object-injection
sink.

Fix: ``SOLR_MULTIVALUE``, ``SOLR_RELATION`` and ``SOLR_CLASSIFICATION`` return their payload as
``json_encode($array)``, and :php:`AbstractIndexer` together with :php:`PageFieldMappingIndexer`
decodes it using ``json_decode($string, true)``, keeping the result only when it is an array.
``json_decode()`` never reconstructs PHP objects, so the sink is gone rather than filtered, and the
``try``/``catch`` that used to swallow ``@unserialize()`` failures is removed with it.

Also released for TYPO3 13 LTS as EXT:solr 13.1.4.

SST ticket: #2026011610000017, dkd: #235568


Security: CVE-2026-56094 — Request Filters Can No Longer Preempt the siteHash Filter
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Request-provided ``tx_solr[additionalFilters]`` could register a named ``siteHash`` filter, and
:php:`AbstractQueryBuilder::useFilter()` refused to overwrite an existing name — so the system
``siteHash`` filter that :php:`AccessComponent` adds later was dropped. On an installation where
several sites share one Solr core, that let an anonymous visitor of one site read the public
documents of another.

Fix: the reserved names ``siteHash`` and ``access`` are stripped from request-provided
``additionalFilters`` before the query is built, and the system ``siteHash`` filter is applied with
remove-then-set semantics, symmetric with what :php:`useUserAccessGroups()` already did. Filters an
integrator sets server-side are unaffected — TypoScript ``plugin.tx_solr.search.query.filter``,
plugin and FlexForm settings, and PSR-14 events all still apply.

Also released for TYPO3 13 LTS as EXT:solr 13.1.4.

SST ticket: #2026052010000029, dkd: #235572


Security: CVE-2026-56093 — Detail View Enforces Site and Access Restrictions
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The ``detail`` action of the ``pi_results`` plugin looked a document up by its ``documentId``
without applying the current site's ``siteHash`` filter or the frontend user-group access filter.
An anonymous visitor who knew or guessed a valid ``documentId`` could pull access-restricted
documents out of the detail view, a path that was less restricted than the regular search.

Fix: :php:`SearchResultSetService::getDocumentById()` runs the by-id query through
:php:`AccessComponent`, the same listener the regular search path uses, so both apply the same
``siteHash`` and user-group filters. A ``documentId`` that resolves to no accessible document —
unknown, belonging to another site, or restricted for the current visitor — makes ``detailAction``
fail closed with the site's configured 404 page, propagated as the whole response. That response is
uniform, so it cannot be used to probe whether a restricted document exists.

As defence in depth, review whether your templates still expose ``data-document-id`` and mask it
where the document id should not be publicly visible.

Also released for TYPO3 13 LTS as EXT:solr 13.1.4.

SST ticket: #2026052010000011, dkd: #235573


Security: CVE-2026-56092 — No Forged fe_group/extendToSubpages on Pages Records
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

On TYPO3 13 (EXT:solr 13.1.x), a page-indexer sub-request forced ``fe_group`` and
``extendToSubpages`` to public values on every ``pages`` record it touched, and TYPO3 Core's
``RootlineUtility`` persisted that forged record into the shared, cross-request ``rootline``
cache — letting an anonymous visitor reach a page whose access restriction was only inherited
via ``extendToSubpages`` from an ancestor page (CVE-2026-56092, fixed in 13.1.4).

EXT:solr's TYPO3 14 indexing pipeline was already unaffected in practice, due to its two-phase
sub-request design. The same listener has been removed here too, as a hardening measure: the
access bypass it needed during indexing is already provided safely, without touching any
persisted cache, by EXT:solr's other listeners.

Also released for TYPO3 13 LTS as EXT:solr 13.1.4.

SST ticket: #2026052210000016, dkd: #235574


Breaking Changes
----------------

!!! jQuery Removed: Vanilla JavaScript Frontend
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The jQuery, jQuery UI, and associated plugin dependencies have been removed
from all frontend JavaScript. All controllers (search, suggest, facet options,
date range, numeric range) have been rewritten in vanilla JavaScript.
The `autoComplete.js <https://tarekraafat.github.io/autoComplete.js/#/>`_ library
is now used for the suggest/autocomplete feature.

New dedicated CSS files (``daterange.css``, ``numericrange.css``) replace the
bundled jQuery UI stylesheet (``jquery-ui.custom.css``).

*Migration:* Remove any ``solr-jquery``, ``solr-uri-jquery``, and ``solr-ui``
(jQuery UI) asset references from your TypoScript includes and replace them
with the updated example templates. See the *Frontend / Autosuggest*,
*Frontend / Facets*, and *Frontend / Ajax* documentation chapters for updated examples.

.. note::
   If you have overridden any of the following Fluid templates, review the
   changes in EXT:solr and update your overrides accordingly, as the
   ``data-*`` attributes and markup structure have changed:

   *  ``Resources/Private/Partials/Facets/Options.html``
   *  ``Resources/Private/Partials/Facets/OptionsFiltered.html``
   *  ``Resources/Private/Partials/Facets/RangeDate.html``
   *  ``Resources/Private/Partials/Facets/RangeNumeric.html``


Unified Site Hash Strategy
~~~~~~~~~~~~~~~~~~~~~~~~~~

Introduced in solr v13.1, and now implemented as default, the site hash
strategy is now based on the site identifier and not on the domain anymore,
making the site hash calculation more robust across sites with multiple domains.

The extension configuration setting: ``siteHashStrategy`` has been removed
without substitution.

The PSR-14 event :php:`AfterDomainHasBeenDeterminedForSiteEvent` has been
removed, as it has been superseded by
:php:`AfterSiteHashHasBeenDeterminedForSiteEvent`.

If you upgrade from < 13.1, it is recommended to re-index all solr cores
completely.

Finalization: the ``domain_stringS`` and ``typo3Context_stringS`` dynamic fields
previously written by :php:`Builder` have been replaced by dedicated schema fields
``domain`` and ``typo3Context``. The ``domain`` field allows building inter-site
URLs without bootstrapping TYPO3 sites within search contexts, and ``typo3Context``
distinguishes documents by the environment they were indexed from. Re-index to
populate the new fields.

See `#4411 <https://github.com/TYPO3-Solr/ext-solr/issues/4411>`_.


!!! QueueInitializationServiceAwareInterface and related Queue methods removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The interface
:php:`ApacheSolrForTypo3\Solr\IndexQueue\QueueInitializationServiceAwareInterface`
and its implementation in :php:`ApacheSolrForTypo3\Solr\IndexQueue\Queue` have been
removed entirely. The following public API is gone:

*   :php:`Queue::setQueueInitializationService(QueueInitializationService $service): void`
*   :php:`Queue::getQueueInitializationService(): QueueInitializationService`
*   :php:`Queue::getInitializationService(): QueueInitializationService` (was already deprecated since v12)

The :php:`QueueInitializationService` itself is not affected and continues to exist.

Background
""""""""""

The interface was introduced as a workaround for a circular dependency: the
:php:`QueueInitializationService` created :php:`Queue` instances and then injected itself
back via :php:`setQueueInitializationService()`. In practice, the injected service was
never used by :php:`Queue` internally, and :php:`getQueueInitializationService()` was
only called in tests – never in production code. The pattern was obsolete.


!!! DataUpdateHandler::removeFromIndexAndQueueWhenItemInQueue() removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The deprecated method :php:`DataUpdateHandler::removeFromIndexAndQueueWhenItemInQueue(string $recordTable, int $recordUid): void`
has been removed. Use :php:`DataUpdateHandler::removeFromIndexAndQueue()` directly instead.

Impact
""""""

**Code overriding** :php:`removeFromIndexAndQueueWhenItemInQueue()` or calling it from a subclass

Replace every call to :php:`removeFromIndexAndQueueWhenItemInQueue()` with a direct call to :php:`removeFromIndexAndQueue()`.

The queue-containment check that was part of the old method is not needed:
:php:`removeFromIndexAndQueue()` / :php:`GarbageHandler::collectGarbage()` handle that case gracefully.


!!! PageIndexer::isPageIndexable() removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The deprecated method :php:`PageIndexer::isPageIndexable(Item $item): bool` has been removed.
Use :php:`PageIndexer::isPageEnabled(array $record): bool` instead.

Impact
""""""

**Code overriding** :php:`isPageIndexable()` in a custom :php:`PageIndexer` subclass

Rename method :php:`isPageIndexable` to :php:`isPageEnabled()` and adjust the signature to accept an array
(the page record) instead of an :php:`Item` object:


!!! RecordUpdatedEvent no longer covers record insertions – use RecordInsertedEvent
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

A dedicated :php:`RecordInsertedEvent` has been introduced for record creations.
The :php:`RecordUpdatedEvent` now only fires for updates; the deprecated
:php:`$isNewRecord` property, its constructor parameter, and the :php:`isNewRecord()`
method have been removed from :php:`RecordUpdatedEvent`.

Previously :php:`RecordUpdatedEvent` was dispatched for both new records and updates,
with :php:`isNewRecord()` acting as a flag to distinguish the two cases.
In v14 these are two distinct events.

Impact
""""""

**Listeners checking** :php:`$event->isNewRecord()`

Register your listener for :php:`RecordInsertedEvent` to handle creations, and for
:php:`RecordUpdatedEvent` to handle updates. Remove any :php:`isNewRecord()` checks:

..  code-block:: yaml

    # Before – one listener covering both cases
    tags:
      - name: event.listener
        event: ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent

    # After – separate registrations
    tags:
      - name: event.listener
        event: ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordInsertedEvent
      - name: event.listener
        event: ApacheSolrForTypo3\Solr\Domain\Index\Queue\UpdateHandler\Events\RecordUpdatedEvent

**Code instantiating** :php:`new RecordUpdatedEvent(..., isNewRecord: true)`

Replace with :php:`new RecordInsertedEvent($uid, $table, $fields)`.


!!! Item properties are now non-nullable with strict validation
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:php:`Item` constructor properties (``item_uid``, ``indexing_configuration``, ``changed``)
are now non-nullable and validated strictly. CSV fixtures for integration tests
must include all required columns.


!!! Legacy PageIndexer system removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The HTTP-based page indexing system has been completely removed and replaced by
the unified sub-request pipeline. The following classes no longer exist:

*   :php:`IndexQueue\PageIndexer` — replaced by :php:`IndexQueue\IndexingService`
*   :php:`IndexQueue\PageIndexerRequest` — replaced by :php:`IndexQueue\IndexingInstructions`
*   :php:`IndexQueue\PageIndexerResponse` — replaced by ``JsonResponse``
*   :php:`IndexQueue\PageIndexerRequestHandler`
*   :php:`IndexQueue\PageIndexerDataUrlModifier` (interface)
*   :php:`IndexQueue\FrontendHelper\Manager`
*   :php:`IndexQueue\FrontendHelper\FrontendHelper` (interface)
*   :php:`IndexQueue\FrontendHelper\PageIndexer` (event listener)
*   :php:`Middleware\PageIndexerInitialization`
*   :php:`System\Environment\CliEnvironment`
*   :php:`System\Environment\WebRootAllReadyDefinedException`

The ``UserGroupDetector`` and ``AuthorizationService`` have been moved from
:php:`IndexQueue\FrontendHelper` to the :php:`Middleware` namespace.

The ``forcedWebRoot`` scheduler task option has been removed from
``IndexQueueWorkerTask`` and ``IndexQueueWorkerTaskAdditionalFieldProvider``.

Impact
""""""

**Custom PageIndexer subclasses** must be rewritten to use the new pipeline.
Register event listeners for :php:`AfterPageDocumentIsCreatedForIndexingEvent`
or :php:`BeforeDocumentIsProcessedForIndexingEvent` instead.

**Code referencing** :php:`PageIndexerRequest::SOLR_INDEX_HEADER` (``X-Tx-Solr-Iq``)
should check the ``solr.indexingInstructions`` request attribute instead.

**Code using** :php:`CliEnvironment` for web root initialization should remove
those calls — the sub-request pipeline handles CWD automatically.


!!! A failed Index Queue item states why it failed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

An item whose indexing failed without an exception kept ``changed > indexed`` and no error,
so every run fetched it again and the Index Queue stopped making progress without showing a
reason. Such an item is now marked as failed.

The reason is recorded as well. Executing an indexing sub-request caught every throwable,
wrote one log entry and reported the item as "not indexed", so the cause was only in the
TYPO3 log. It is handed on now and ends up on the item, naming the sub-request action, the
item and the language.

``tx_solr_indexqueue_item.errors`` is a ``mediumtext`` for that. The messages of the whole
exception chain are recorded, the stack traces stay in the TYPO3 log: a sub-request nests
the same throwable several times, and all of its traces together exceeded the previous
column, which turned marking an item as failed into a "Data too long" error.

Impact
""""""

**The column change needs a database update**, so this does not take effect by updating the
extension alone. Until the update ran, an item that fails with a long enough reason still
aborts the indexing run:

#.  Run *Analyze Database Structure* in the Upgrade module, or
    ``vendor/bin/typo3 extension:setup``.
#.  Empty ``tx_solr_indexqueue_item``.
#.  Re-initialize the Index Queue, which also takes care of the items described in the next
    section.

.. note::
   One reason that becomes visible with this is a TYPO3 issue rather than one of EXT:solr.
   Indexing from the Index Queue module can fail on an installation with a single site, where
   the module selects that site by itself and the page tree has page ``0`` selected while
   *Index Now* is clicked. Fluid then aborts with "No Content Object definition found at
   TypoScript object path", because ``f:cObject`` took the TypoScript from the backend context
   instead of the indexing sub-request. TYPO3 solves it in
   `6ceccf85 <https://github.com/TYPO3/typo3/commit/6ceccf85b31389c193dfd7feb97b3ff8b39ba1ce>`_,
   part of TYPO3 14.3.7.


!!! Index Queue initialization stops at nested site roots
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Initializing the Index Queue no longer collects the pages, and the records
stored on them, of a site nested inside another site's page tree. A page
carrying ``is_siteroot`` starts a site of its own, so its subtree belongs to
that site's Index Queue alone.

Until now the traversal ran through the whole subtree, so an outer site's queue
also held the inner site's pages, with the outer site's root page written into
``tx_solr_indexqueue_item.root``. Indexing such an item took the Solr
connections from the outer site while the page itself resolved to the inner one.
TYPO3 assigns language ids per site, the two sets need not agree, and core will
not reconcile them -- see the closing of
`forge #95688 <https://forge.typo3.org/issues/95688>`__ and its conclusion that
"the only real solution is to define languages per page tree root". Such an item
either failed with "Language X does not exist on site Y", or produced a document
carrying one site's ``siteHash`` in another site's core, where neither site
could find it.

:php:`Site::getPagesWithinSite()` and
:php:`PagesRepository::findAllSubPageIdsByRootPageWithinSite()` are new.
:php:`Site::getPages()` and :php:`PagesRepository::getTreeList()` keep their
previous behaviour, so mount point resolution and garbage collection are
unaffected.

Impact
""""""

**Re-initialize the Index Queue after updating.** Items written before this
release still carry the outer site's root page and are not corrected
automatically.

**A site that relied on indexing a nested site's pages loses those documents**
from its own cores; the nested site indexes them into its cores instead. Two
supported ways to keep the previous search results:

*   ``plugin.tx_solr.search.query.allowedSites`` lets one search span several
    sites. This is the intended way to search across a shared page tree, and it
    keeps each site's documents in its own cores.
*   ``plugin.tx_solr.index.queue.[indexConfig].additionalPageIds`` still adds
    individual pages explicitly, a nested site's pages included. It stays the
    opt-in for an outer site that really has to index foreign pages, and the
    language ids of the sites involved have to match for that to work.


!!! The ``indexer`` Index Queue setting has been removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``plugin.tx_solr.index.queue.[indexConfig].indexer`` and its options array
``indexer.`` no longer exist, together with the accessors
:php:`TypoScriptConfiguration::getIndexQueueIndexerByConfigurationName()` and
:php:`TypoScriptConfiguration::getIndexQueueIndexerConfigurationByConfigurationName()`.

The setting was honoured up to and including 13.1.x. It stopped taking effect
during 14 development, when the legacy page indexer was removed: no code read
either accessor any more, while the shipped default still named
:php:`ApacheSolrForTypo3\Solr\IndexQueue\PageIndexer`, a class that removal had
deleted. Removing the setting outright replaces that silence with a documented
migration path.

Impact
""""""

**Upgrading from 13.1.x with a custom** ``indexer`` **is a breaking change.** The
class was called there and is not called any more, and leaving it configured
produces no error -- the records it used to index simply stop being indexed the
way it indexed them. Move its logic into event listeners as part of the upgrade.

**Coming from a 14 development state**, the lines can be dropped without any
further change: no released 14 version ever honoured them.

**Custom indexer classes** are replaced by event listeners, the same ones that
replace a :php:`PageIndexer` subclass: :php:`AfterPageDocumentIsCreatedForIndexingEvent`
and :php:`BeforeDocumentIsProcessedForIndexingEvent` to shape a document,
:php:`BeforeItemsAreIndexedEvent` and :php:`BeforeDocumentsAreIndexedEvent` to act on
a whole batch. Field mapping stays in
``plugin.tx_solr.index.queue.[indexConfig].fields``.

:php:`ApacheSolrForTypo3\Solr\IndexQueue\Indexer` **is removed with the setting**,
since it was the class the setting named and nothing else referenced it. A
subclass of it stops being instantiated, so the same migration to event listeners
applies. What it did lives in the sub-request pipeline:
:php:`IndexingService` builds the request and resolves the Solr connections,
:php:`SolrIndexingMiddleware` creates, processes and submits the documents and
fires the events above, and :php:`RecordFieldMapper` maps the fields.


!!! Trailing Space Removed from ``searchResultClassName`` and ``searchResultSetClassName`` Keys
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The configuration keys
``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName ']`` and
``$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName ']``
had an unintentional trailing space. The space has been removed.

Impact
""""""

**Code registering a custom search result class** using the old key with a trailing space will
silently fall back to the default class, since the key no longer matches.

Migration
"""""""""

Remove the trailing space from the key in your ``ext_localconf.php``:

..  code-block:: php

    // Before (broken — trailing space)
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName '] = MySearchResult::class;
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName '] = MySearchResultSet::class;

    // After (correct)
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultClassName'] = MySearchResult::class;
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['searchResultSetClassName'] = MySearchResultSet::class;


!!! Upgrade to Apache Solr 10.0.0
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Latest Apache Solr Release 10.0.0 required, EXT:solr 14 won't support older Apache Solr versions. Along with the switch to Apache Solr 10, Apache Lucene 10 is being used.
A full reindexing is recommended. Please refer to the Apache Solr documentation to find out what major changes Solr 10 brings.

Solr 10 bundles Jetty 12, which strictly rejects ambiguous URI path encoding (HTTP 400). A previous workaround using double rawurlencode() for the managed synonyms and stopwords
API has been removed, as it is incompatible with Jetty 12. Synonym base words and stop words containing non-ASCII characters (e.g. umlauts) are now handled correctly.

In Solr 10 the LocalTikaExtractionBackend (deprecated since Solr 9.10, SOLR-17961) was also removed. The tikaserver backend is now the only supported extraction backend. The `/update/extract`
request handler has been removed from solrconfig.xml accordingly. Users relying on Solr Cell must use EXT:tika v14+ and provide a Tika Server or Tika app

..  warning::
    Synonym base words containing "%" or "/" remain unsupported. "%" is rejected by
    Jetty 12 as potentially ambiguous, and "/" is interpreted as a URI path separator by Solr.
    See: https://issues.apache.org/jira/browse/SOLR-6853

..  warning::
    Users relying on Solr Cell must use EXT:tika v14+ and provide a Tika Server or Tika app and every usage of `SolrWriteService->extractByQuery()` must be refactored to use EXT:tika.


!!! Deprecated dynamic Solr fields dropped
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Since EXT:solr 9 and Apache Solr 7 dynamic fields based on trie fields are marked as deprecated, these fields are now removed:

*   *_tIntS (-> *_intS)
*   *_tInt (-> _intM)
*   *_tLong (-> _longS)
*   *_tLong (-> _longM)
*   *_tFloat (-> _floatS)
*   *_tFloat (-> _floatM)
*   *_tDouble (-> _doubleS)
*   *_tDouble (-> _doubleS)
*   *_tDouble4 (-> _double4S)
*   *_tDouble4 (-> _double4M)
*   *_tDate (-> _dateS)
*   *_tDate (-> _dateM)


!!! Highlighting::getUseFastVectorHighlighter() removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:php:`ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder\Highlighting::getUseFastVectorHighlighter()`
returned whether ``fragmentSize`` was large enough (``>= 18``) for the FastVector Highlighter.
Since the Unified Highlighter is now used unconditionally (see CVE-2026-56096 above),
the method had no effect on the built query.
It was deprecated in 13.1.4 and is removed in 14.0.0.

The related query parameter ``hl.useFastVectorHighlighter`` is no longer sent;
``hl.method=unified`` is sent instead.

*Migration:* Drop the call. There is no replacement — the highlighter is no longer switchable via ``fragmentSize``.


!!! Query syntax in ``tx_solr[q]`` is escaped and field selectors are whitelisted
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Raw Lucene syntax typed by a visitor no longer reaches Solr verbatim (see CVE-2026-56096 above).
Selector, range and grouping characters are escaped at the input boundary, and ``field:value``
only resolves for fields on the edismax ``uf`` whitelist, which defaults to the field list of
``plugin.tx_solr.search.query.queryFields``.

A frontend that lets visitors type ``field:value`` against fields outside ``queryFields``,
or that relies on ``[a TO b]`` ranges in ``tx_solr[q]``, now misses silently instead of matching.

*Migration:* Put the fields a visitor may address into
``plugin.tx_solr.search.query.userFields`` — either as a scalar override or through the ``add``
sub-key. Range and grouping syntax in the user query has no replacement; express such
constraints as integrator-controlled ``plugin.tx_solr.search.query.filter`` entries instead.


!!! Multi-value cObjs use JSON transport
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``SOLR_MULTIVALUE``, ``SOLR_RELATION`` and ``SOLR_CLASSIFICATION`` return ``json_encode($array)``
where they used to return ``serialize($array)`` (see CVE-2026-56095 above), and the indexer decodes
the payload with ``json_decode()`` only.

*Migration:* A third-party content object that returns ``serialize($array)`` for a multi-value Solr
field has to switch to ``json_encode($array)``. Nothing else changes, the decoded payload still has
to be an array to be taken over.

The same principle applies to a custom indexing implementation that populates a multi-value Solr
field directly: the value assigned to the field has to be JSON-encoded rather than PHP-serialized.
A listener on :php:`BeforeDocumentsAreIndexedEvent` that derives one or more coordinate sets from
the indexed record assigns them like this:

..  code-block:: php

    public function __invoke(BeforeDocumentsAreIndexedEvent $event): void
    {
        $indexQueueItem = $event->getIndexQueueItem();

        $coordinates = json_encode(
            $this->extractCoordinatesFromRecord(
                $indexQueueItem->getRecord(),
                $indexQueueItem->getType(),
            ),
            JSON_UNESCAPED_UNICODE,
        );

        $event->getDocument()->setField('###solr-field-name-here###', $coordinates);
    }

What matters is that the complete multi-value array coming out of the extraction logic goes through
``json_encode()`` before it is assigned to the Solr field. An extracted value such as

..  code-block:: php

    [
        [
            'latitude' => 48.135125,
            'longitude' => 11.581981,
        ],
    ]

is transported as JSON:

..  code-block:: json

    [{"latitude":48.135125,"longitude":11.581981}]

Multiple values become additional objects in the same JSON array:

..  code-block:: json

    [
        {"latitude":48.135125,"longitude":11.581981,"city":"Munich"},
        {"latitude":50.110924,"longitude":8.682127,"city":"Frankfurt"},
        {"latitude":53.551086,"longitude":9.993682,"city":"Hamburg"}
    ]

Neither the extraction logic nor the structure of the PHP array has to change. The transport format
is the whole migration: ``json_encode($array)`` where ``serialize($array)`` used to stand.


!!! ``tx_solr[additionalFilters]`` can no longer set siteHash or access
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

A frontend request can no longer reach the ``siteHash`` and ``access`` filters through
``tx_solr[additionalFilters]`` (see CVE-2026-56094 above). Both names are stripped from request
input, so a filter passed under either name is silently ignored rather than applied.

*Migration:* Configure cross-site search server-side through
``plugin.tx_solr.search.query.allowedSites``, documented in
:ref:`configuration.reference.solrsearch`. Any other filter name keeps working from the request
exactly as before.


!!! The detail action answers 404 for an inaccessible document
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

``detailAction`` used to render its template whatever the ``documentId`` resolved to (see
CVE-2026-56093 above). It now propagates the site's configured 404 response when the lookup finds
no document the current visitor may see — unknown id, another site's document, or one restricted to
a frontend user group the visitor is not in.

*Migration:* A template or integration that relied on the detail view rendering an empty document
has to handle the 404 instead. Linking to a document from the result list is unaffected, since the
visitor who saw it in the results may see it in the detail view too.


!!! UserGroupDetector::clearPageOverlayAccessRestrictions() removed
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

:php:`ApacheSolrForTypo3\Solr\Middleware\UserGroupDetector::clearPageOverlayAccessRestrictions()`
listened to :php:`BeforeRecordLanguageOverlayEvent` and stripped ``fe_group`` and
``extendToSubpages`` from ``pages`` records during indexing sub-requests
(see CVE-2026-56092 above). The method and its listener registration are removed.

*Migration:* Drop any call or override. There is no replacement — the access bypass the indexer
needs is granted by the remaining listeners, which never reach a persisted cache.


All Changes
-----------

*   [SECURITY] Remove fe_group/extendToSubpages-forging listener (CVE-2026-56092) by @dkd-kaehm in `#4763 <https://github.com/TYPO3-Solr/ext-solr/pull/4763>`_
*   [SECURITY] Fix CVE-2026-56093 — Enforce siteHash and access filters in detailAction lookup by @dkd-kaehm in `#4763 <https://github.com/TYPO3-Solr/ext-solr/pull/4763>`_
*   [SECURITY] Fix CVE-2026-56094 — Prevent request additionalFilters from preempting siteHash filter by @dkd-kaehm in `#4763 <https://github.com/TYPO3-Solr/ext-solr/pull/4763>`_
*   [DOCS] Expand multi-value cObjs section with an example by @dkd-kaehm in `#4763 <https://github.com/TYPO3-Solr/ext-solr/pull/4763>`_
*   [!!!][SECURITY] Fix CVE-2026-56095 — JSON transport for multi-value cObjs by @dkd-kaehm in `#4763 <https://github.com/TYPO3-Solr/ext-solr/pull/4763>`_
*   [DOCS] Document the Unified Highlighter default summary by @dkd-kaehm in `#4763 <https://github.com/TYPO3-Solr/ext-solr/pull/4763>`_
*   [SECURITY] Fix CVE-2026-56096 — escape user query syntax by @dkd-kaehm in `#4763 <https://github.com/TYPO3-Solr/ext-solr/pull/4763>`_
*   [SECURITY] Fix CVE-2026-56096 — edismax uf whitelist by @dkd-kaehm in `#4763 <https://github.com/TYPO3-Solr/ext-solr/pull/4763>`_
*   [TASK] Move the request initialization case to IndexingService by @dkd-kaehm in `#4760 <https://github.com/TYPO3-Solr/ext-solr/pull/4760>`_
*   [TASK] Move the Solr write status case, and assert a status code again by @dkd-kaehm in `#4760 <https://github.com/TYPO3-Solr/ext-solr/pull/4760>`_
*   [TASK] Move the added documents case to the middleware by @dkd-kaehm in `#4760 <https://github.com/TYPO3-Solr/ext-solr/pull/4760>`_
*   [!!!][TASK] Remove the Index Queue Indexer by @dkd-kaehm in `#4760 <https://github.com/TYPO3-Solr/ext-solr/pull/4760>`_
*   [TASK] Assert the added documents event through the indexing pipeline by @dkd-kaehm in `#4760 <https://github.com/TYPO3-Solr/ext-solr/pull/4760>`_
*   [TASK] Fill Solr through the production pipeline in the remaining tests by @dkd-kaehm in `#4760 <https://github.com/TYPO3-Solr/ext-solr/pull/4760>`_
*   [TASK] Assert connection resolution against IndexingService by @dkd-kaehm in `#4760 <https://github.com/TYPO3-Solr/ext-solr/pull/4760>`_
*   [TASK] Index records through the production pipeline in IndexerTest by @dkd-kaehm in `#4760 <https://github.com/TYPO3-Solr/ext-solr/pull/4760>`_
*   [TASK] Drop the claim that indexPages() fakes a sub-request by @dkd-kaehm in `#4760 <https://github.com/TYPO3-Solr/ext-solr/pull/4760>`_
*   [!!!][BUGFIX] Hand on why an indexing sub-request failed by @dkd-kaehm in `#4759 <https://github.com/TYPO3-Solr/ext-solr/pull/4759>`_
*   [!!!][BUGFIX] Stop Index Queue initialization at nested site roots by @dkd-kaehm in `#4759 <https://github.com/TYPO3-Solr/ext-solr/pull/4759>`_
*   [TASK] Explain why a language is missing on the page's site by @dkd-kaehm in `#4759 <https://github.com/TYPO3-Solr/ext-solr/pull/4759>`_
*   [BUGFIX] Mark index queue items as failed when indexing returns false by @dkd-kaehm in `#4759 <https://github.com/TYPO3-Solr/ext-solr/pull/4759>`_
*   [DOCS] Point integrators at the indexing events instead of the indexer setting by @dkd-kaehm in `#4758 <https://github.com/TYPO3-Solr/ext-solr/pull/4758>`_
*   [TASK] Retire the indexer Index Queue setting by @dkd-kaehm in `#4758 <https://github.com/TYPO3-Solr/ext-solr/pull/4758>`_
*   [TASK] Drop the dead indexer setting from the integration fixtures by @dkd-kaehm in `#4758 <https://github.com/TYPO3-Solr/ext-solr/pull/4758>`_
*   [TASK] Stop shipping an indexer default that names a removed class by @dkd-kaehm in `#4758 <https://github.com/TYPO3-Solr/ext-solr/pull/4758>`_
*   [TASK] Retire the hand-built IndexingInstructions from the test base by @dkd-kaehm in `#4756 <https://github.com/TYPO3-Solr/ext-solr/pull/4756>`_
*   [TASK] Index the garbage collector fixtures through IndexService by @dkd-kaehm in `#4756 <https://github.com/TYPO3-Solr/ext-solr/pull/4756>`_
*   [TASK] Index queued pages through the production pipeline in PageIndexerTest by @dkd-kaehm in `#4756 <https://github.com/TYPO3-Solr/ext-solr/pull/4756>`_
*   [TASK] Enable the example page indexer through TypoScript by @dkd-kaehm in `#4756 <https://github.com/TYPO3-Solr/ext-solr/pull/4756>`_
*   [TASK] Add a production entry for indexing a single queue item by @dkd-kaehm in `#4756 <https://github.com/TYPO3-Solr/ext-solr/pull/4756>`_
*   [TASK] Index pages through the production pipeline in integration tests by @dkd-kaehm in `#4755 <https://github.com/TYPO3-Solr/ext-solr/pull/4755>`_
*   [TASK] Address tied search results by value instead of by position by @dkd-kaehm in `#4755 <https://github.com/TYPO3-Solr/ext-solr/pull/4755>`_
*   [TASK] Swap the testing IndexingService only once per test by @dkd-kaehm in `#4755 <https://github.com/TYPO3-Solr/ext-solr/pull/4755>`_
*   [TASK] Reset the index time when requeueing a page in integration tests by @dkd-kaehm in `#4755 <https://github.com/TYPO3-Solr/ext-solr/pull/4755>`_
*   [TASK] Guard against silently losing a test case by @dkd-kaehm in `#4754 <https://github.com/TYPO3-Solr/ext-solr/pull/4754>`_
*   [TASK] Drop @var docblocks that restate the property type by @dkd-kaehm in `#4754 <https://github.com/TYPO3-Solr/ext-solr/pull/4754>`_
*   [TASK] Declare test class properties with native types by @dkd-kaehm in `#4754 <https://github.com/TYPO3-Solr/ext-solr/pull/4754>`_
*   [TASK] Drop the always-true array guard in QueueTest by @dkd-kaehm in `#4754 <https://github.com/TYPO3-Solr/ext-solr/pull/4754>`_
*   [TASK] Align the inaccessible property helpers of both test bases by @dkd-kaehm in `#4738 <https://github.com/TYPO3-Solr/ext-solr/pull/4738>`_
*   [FEATURE] Add BeforeIndexingSubRequestIsPreparedEvent by @dkd-kaehm in `#4738 <https://github.com/TYPO3-Solr/ext-solr/pull/4738>`_
*   [BUGFIX] Remove not needed chdir in IntegrationTestBase::setUp() by @dkd-kaehm in `#4738 <https://github.com/TYPO3-Solr/ext-solr/pull/4738>`_
*   [TASK] Replace deprecated DocHeader APIs in AbstractModuleController by @dkd-kaehm in `#4738 <https://github.com/TYPO3-Solr/ext-solr/pull/4738>`_
*   [BUGFIX] Replace deprecated GeneralUtility::getIndpEnv() calls by @dkd-kaehm in `#4738 <https://github.com/TYPO3-Solr/ext-solr/pull/4738>`_
*   [TASK] Minor housekeeping in issue template and CI matrix by @dkd-kaehm in `#4738 <https://github.com/TYPO3-Solr/ext-solr/pull/4738>`_
*   [SECURITY] Fix CVE-2026-56096 — close FVH FieldExistsQuery HTTP 500 oracle by @dkd-kaehm in `#4751 <https://github.com/TYPO3-Solr/ext-solr/pull/4751>`_
*   [BUGFIX] Prevent undefined array key warning in SettingsPreviewOnPluginsEventListener by @mschwemer in `#4686 <https://github.com/TYPO3-Solr/ext-solr/pull/4686>`_
*   [BUGFIX] Apply ASCII folding before stemming in all language schemas by @tgaertner in `#4741 <https://github.com/TYPO3-Solr/ext-solr/pull/4741>`_
*   [BUGFIX] fix autosuggestion initialization for multiple search forms on one page by @dkd-lehnebach in `#4747 <https://github.com/TYPO3-Solr/ext-solr/pull/4747>`_
*   [TASK] allow GH Actions on security releases relevant branches and PRs by @dkd-kaehm in `d0d1164b8 <https://github.com/TYPO3-Solr/ext-solr/commit/d0d1164b896a14732de21d687daef135507eda84>`_
*   [TASK] Remove obsolete "addRootLineFields" setting by @tillhoerner in `#4735 <https://github.com/TYPO3-Solr/ext-solr/pull/4735>`_
*   [BUGFIX] Disable page cache before page indexing sub-requests by @amirarends in `d36c876c4 <https://github.com/TYPO3-Solr/ext-solr/commit/d36c876c44a8ae77027a88583b7051bd7c341c7f>`_
*   [BUGFIX] Restore Context aspects after indexing sub-request by @hdj-typoconsult in `#4733 <https://github.com/TYPO3-Solr/ext-solr/pull/4733>`_
*   [BUGFIX] Prevent PHP warning when building the access rootline for un-collected Index Queue pages by @konradmichalik in `#4728 <https://github.com/TYPO3-Solr/ext-solr/pull/4728>`_
*   [DOCS] Update links to Apache Solr Reference Guide by @tillhoerner in `#4736 <https://github.com/TYPO3-Solr/ext-solr/pull/4736>`_
*   [BUGFIX] Make PostBigRequest listener opt-in via extension config by @wazum in `#4631 <https://github.com/TYPO3-Solr/ext-solr/pull/4631>`_
*   [BUGFIX] Convert long Solr GET requests to POST on PSR-14 dispatcher by @wazum in `#4631 <https://github.com/TYPO3-Solr/ext-solr/pull/4631>`_
*   [TASK] Make EXT:install an optional dependency by @wazum in `#4676 <https://github.com/TYPO3-Solr/ext-solr/pull/4676>`_
*   [BUGFIX] PHPStan issues 2026.08.12 by @dkd-kaehm in `#4731 <https://github.com/TYPO3-Solr/ext-solr/pull/4731>`_
*   [DOCS] Mention requirements for sortable fields by @kitzberger in `#4726 <https://github.com/TYPO3-Solr/ext-solr/pull/4726>`_
*   [DOCS] Mention requirements for searchable fields by @kitzberger in `#4726 <https://github.com/TYPO3-Solr/ext-solr/pull/4726>`_
*   [CLEANUP] Remove unneeded dependency on fluid-styled-content by @dmitryd in `#4729 <https://github.com/TYPO3-Solr/ext-solr/pull/4729>`_
*   [BUGFIX] Fix leaked language Context aspect between indexing sub-requests by @BastiLu in `#4703 <https://github.com/TYPO3-Solr/ext-solr/issues/4703>`_
*   [TASK] Finalize site hash by site-identifier: add ``domain``/``typo3Context`` schema fields, drop ``_stringS`` suffixes in Builder by @dkd-kaehm in `#4411 <https://github.com/TYPO3-Solr/ext-solr/issues/4411>`_
*   !!![TASK] Update solarium/solarium requirement from 6.4.1 to 7.0.0 — removes ``AbstractQueryBuilder::removeOperator()`` and ``removeAlternativeQuery()`` without alternatives by @dependabot in `#4713 <https://github.com/TYPO3-Solr/ext-solr/pull/4713>`_
*   [TASK] Prevent unnecessary complete loading of record in SettingsPreviewOnPluginsEventListener by @un3us in `#4691 <https://github.com/TYPO3-Solr/ext-solr/pull/4691>`_
*   [BUGFIX] Fix undefined array key exception in flexParentDatabaseRow by @BastiLu in `#4698 <https://github.com/TYPO3-Solr/ext-solr/pull/4698>`_
*   [BUGFIX] Don't leak page title state between indexing sub-requests by @amirarends in `#4700 <https://github.com/TYPO3-Solr/ext-solr/issues/4700>`_
*   [FEATURE] Log the PID where the request failed by @guelzow in `#4711 <https://github.com/TYPO3-Solr/ext-solr/issues/4711>`_
*   [BUGFIX] Spellchecking: keep correct terms when offering corrections by @dkd-kaehm in `#4659 <https://github.com/TYPO3-Solr/ext-solr/issues/4659>`_
*   [TASK] Refactor scheduler tasks to TYPO3 14 API and allow migration by @dkd-kaehm in `#4525 <https://github.com/TYPO3-Solr/ext-solr/issues/4525>`_
*   [TASK] Update to PHPStan 2 by @dkd-kaehm in `#4710 <https://github.com/TYPO3-Solr/ext-solr/pull/4710>`_
*   [DOCS] Update Indexing.rst: clearing documents only for current site by @pi-phi in `#4672 <https://github.com/TYPO3-Solr/ext-solr/pull/4672>`_
*   [BUGFIX] Fix Scheduler::__construct() call with missing 4th arg in integration tests by @dkd-kaehm in `#4678 <https://github.com/TYPO3-Solr/ext-solr/pull/4678>`_
*   [TASK] Remove guzzlehttp/psr7 <2.10.0 pin (upstream fix in guzzlehttp/guzzle 7.10.2) by @dkd-kaehm in `#4660 <https://github.com/TYPO3-Solr/ext-solr/issues/4660>`_
*   [BUGFIX] Pin guzzlehttp/psr7 to <2.10.0 by @dkd-kaehm in `#4661 <https://github.com/TYPO3-Solr/ext-solr/pull/4661>`_
*   [BUGFIX] Do not resolve TypoScriptConfiguration for deleted page in WS by @amirarends in `#4603 <https://github.com/TYPO3-Solr/ext-solr/pull/4603>`_
*   [BUGFIX] Make suggest widget form submission preventable by @danilovq in `#4657 <https://github.com/TYPO3-Solr/ext-solr/pull/4657>`_
*   [BUGFIX] Use more robust way to calculate suggest dropdown position that works also with mobile/offcanvas layouts by @dmitryd in `#4652 <https://github.com/TYPO3-Solr/ext-solr/pull/4652>`_
*   [DOCS] Fix case-sensitive X-Tx-Solr-Iq header in BestPractice.rst by @hnadler in `#4656 <https://github.com/TYPO3-Solr/ext-solr/pull/4656>`_
*   [BUGFIX] Preserve BE web context across indexing sub-request by @dkd-kaehm in `#4647 <https://github.com/TYPO3-Solr/ext-solr/pull/4647>`_
*   [BUGFIX] fix suggestion query if routeEnhancer is set by @dkd-lehnebach in `#4644 <https://github.com/TYPO3-Solr/ext-solr/pull/4644>`_
*   [BUGFIX] Improve assertion message in AccessProtectedContentTest by @dkd-kaehm in `#4643 <https://github.com/TYPO3-Solr/ext-solr/pull/4643>`_
*   [BUGFIX] v14: remove extra x character in suggest_controller.js by @dkd-kaehm in `3c7cd960a <https://github.com/TYPO3-Solr/ext-solr/commit/3c7cd960a93f74305e006a5466d63d8c0b09c6da>`_
*   [BUGFIX] facet URL encoding mismatch (spaces) when using urlParameterStyle=assoc by @dkd-hauser in `#4610 <https://github.com/TYPO3-Solr/ext-solr/pull/4610>`_
*   [BUGFIX] Correct field name casing for subTitle and navTitle in TypoScript queryFields by @amirarends in `#4618 <https://github.com/TYPO3-Solr/ext-solr/pull/4618>`_
*   [FEATURE] Add site sets for all registered TypoScript templates by @dmitryd in `#4622 <https://github.com/TYPO3-Solr/ext-solr/pull/4622>`_
*   [DOCS] Add documentation about site sets by @dmitryd in `#4622 <https://github.com/TYPO3-Solr/ext-solr/pull/4622>`_
*   [DOCS] Update DynamicFieldTypes.rst by @daylightsoftware in `#4512 <https://github.com/TYPO3-Solr/ext-solr/pull/4512>`_
*   [TASK] switch to stable/dev TYPO3 14.3.x by @dkd-kaehm in `#4620 <https://github.com/TYPO3-Solr/ext-solr/pull/4620>`_
*   [TASK] replace backend module icons with TYPO3 14 style by @konradmichalik in `#4611 <https://github.com/TYPO3-Solr/ext-solr/pull/4611>`_
*   [BUGFIX] Fix managed synonyms and stopwords API compatibility with Solr 10 by @dkd-dobberkau in `#4562 <https://github.com/TYPO3-Solr/ext-solr/pull/4562>`_
*   [TASK] Drop deprecated Solr fields by @dkd-dobberkau in `#4562 <https://github.com/TYPO3-Solr/ext-solr/pull/4562>`_
*   [TASK] Drop ExtractingRequestHandler for Solr 10 by @dkd-dobberkau in `#4562 <https://github.com/TYPO3-Solr/ext-solr/pull/4562>`_
*   [TASK] Update solr-typo3-plugin to 7.0.0 for Solr 10 by @dkd-dobberkau in `#4562 <https://github.com/TYPO3-Solr/ext-solr/pull/4562>`_
*   [TASK] Update Dockerfile and solr.xml for Solr 10 compatibility by @dkd-dobberkau in `#4562 <https://github.com/TYPO3-Solr/ext-solr/pull/4562>`_
*   [TASK] Apache Solr 10 compatibility for configset by @dkd-dobberkau in `#4562 <https://github.com/TYPO3-Solr/ext-solr/pull/4562>`_
*   [TASK] Adjust reports and status checks by @dkd-dobberkau in `#4562 <https://github.com/TYPO3-Solr/ext-solr/pull/4562>`_
*   [TASK] Increase tmpfs size by @dkd-dobberkau in `#4562 <https://github.com/TYPO3-Solr/ext-solr/pull/4562>`_
*   [!!!][BUGFIX] Remove space in ``searchResultClassName`` and ``searchResultSetClassName`` configuration keys by @beardcoder in `#4226 <https://github.com/TYPO3-Solr/ext-solr/pull/4226>`_
*   [!!!][TASK] Remove jQuery dependency from frontend JavaScript by @dkd-lehnebach in `#4619 <https://github.com/TYPO3-Solr/ext-solr/pull/4619>`_
*   [BUGFIX] Prevent c:0 variant and content leakage on fe_group-restricted pages by @dkd-kaehm in `#4559 <https://github.com/TYPO3-Solr/ext-solr/pull/4559>`_
*   [!!!][TASK] Remove legacy PageIndexer system and migrate to IndexingInstructions by @dkd-kaehm in `#4559 <https://github.com/TYPO3-Solr/ext-solr/pull/4559>`_
*   [TASK] Set CWD to public path during sub-requests and remove CliEnvironment by @dkd-kaehm in `#4559 <https://github.com/TYPO3-Solr/ext-solr/pull/4559>`_
*   [!!!][TASK] Refactor indexing stack to unified TYPO3 core sub-requests by @dkd-kaehm in `#4559 <https://github.com/TYPO3-Solr/ext-solr/pull/4559>`_
*   [TASK] Upgrade to typo3/testing-framework 9.5.0 by @dkd-kaehm in `#4604 <https://github.com/TYPO3-Solr/ext-solr/pull/4604>`_
*   [TASK] Fix IconFactory::mapRecordTypeToIconIdentifier() call for TYPO3 14 by @dkd-kaehm in `#4604 <https://github.com/TYPO3-Solr/ext-solr/pull/4604>`_
*   [TASK] Upgrade GitHub Actions to latest versions by @dkd-kaehm in `#4601 <https://github.com/TYPO3-Solr/ext-solr/pull/4601>`_
*   [TASK] Implement deferred Solr cleanup + fix worker core isolation by @dkd-kaehm in `#4594 <https://github.com/TYPO3-Solr/ext-solr/pull/4594>`_
*   [TASK] Run integration tests without processIsolation by @bmack in `#4594 <https://github.com/TYPO3-Solr/ext-solr/pull/4594>`_
*   [TASK] Implement parallel Solr worker cores for paratest integration tests by @dkd-kaehm in `#4594 <https://github.com/TYPO3-Solr/ext-solr/pull/4594>`_
*   [TASK] Convert AbstractUriViewHelper to instance properties by @dkd-kaehm in `#4594 <https://github.com/TYPO3-Solr/ext-solr/pull/4594>`_
*   [TASK] Refactor event listeners with AsEventListener attribute by @sfroemkenjw in `#4588 <https://github.com/TYPO3-Solr/ext-solr/pull/4588>`_
*   [BUGFIX] GeneralUtility::trimExplode(): Argument #2 ($string) must be of type string, int given by @kitzberger in `#4511 <https://github.com/TYPO3-Solr/ext-solr/pull/4511>`_
*   [BUGFIX] Cast result offset to integer by @saschanowak in `#4529 <https://github.com/TYPO3-Solr/ext-solr/pull/4529>`_
*   [TASK] Refactor and optimize Classification handling by @sfroemkenjw in `#4583 <https://github.com/TYPO3-Solr/ext-solr/pull/4583>`_
*   [TASK] Migrate xlf files of TYPO3 modules to XLIFF format 2.0 by @sfroemkenjw in `#4575 <https://github.com/TYPO3-Solr/ext-solr/pull/4575>`_
*   [TASK] Refactor SettingsPreviewOnPlugins to EventListener by @sfroemkenjw in `#4576 <https://github.com/TYPO3-Solr/ext-solr/pull/4576>`_
*   [TASK] Refactor static function usage in ViewHelpers by @sfroemkenjw in `#4582 <https://github.com/TYPO3-Solr/ext-solr/pull/4582>`_
*   [TASK] Refactor facet ViewHelpers to use shared trait by @sfroemkenjw in `#4580 <https://github.com/TYPO3-Solr/ext-solr/pull/4580>`_
*   [TASK] Fix namespace typo in SearchFormViewHelperTest by @sfroemkenjw in `#4581 <https://github.com/TYPO3-Solr/ext-solr/pull/4581>`_
*   [TASK] Refactor SearchFormViewHelper by @sfroemkenjw in `#4563 <https://github.com/TYPO3-Solr/ext-solr/pull/4563>`_
*   [TASK] Refactor IsStringViewHelperTest to IntegrationTestBase by @sfroemkenjw in `#4578 <https://github.com/TYPO3-Solr/ext-solr/pull/4578>`_
*   [TASK] Simplify test setup in SetUpFacetItemViewHelper by @sfroemkenjw in `#4577 <https://github.com/TYPO3-Solr/ext-solr/pull/4577>`_
*   [TASK] Speed up tests by sending autoCommit for updates by @bmack in `#4565 <https://github.com/TYPO3-Solr/ext-solr/pull/4565>`_
*   [TASK] Refactor UnitTests for Rootline and RootlineElement by @sfroemkenjw in `#4574 <https://github.com/TYPO3-Solr/ext-solr/pull/4574>`_
*   [!!!][TASK] Introduce RecordInsertedEvent, drop isNewRecord from RecordUpdatedEvent by @dkd-friedrich in `#4560 <https://github.com/TYPO3-Solr/ext-solr/pull/4560>`_
*   [!!!][TASK] Remove deprecated DataUpdateHandler::removeFromIndexAndQueueWhenItemInQueue() by @dkd-friedrich in `#4560 <https://github.com/TYPO3-Solr/ext-solr/pull/4560>`_
*   [!!!][TASK] Remove deprecated PageIndexer::isPageIndexable() for v14 by @dkd-friedrich in `#4560 <https://github.com/TYPO3-Solr/ext-solr/pull/4560>`_
*   [!!!][TASK] Remove QueueInitializationServiceAwareInterface and related Queue API by @dkd-friedrich in `#4560 <https://github.com/TYPO3-Solr/ext-solr/pull/4560>`_
*   [TASK] Update test extensions to use 'apache-solr-for-typo3/solr' by @sfroemkenjw in `#4573 <https://github.com/TYPO3-Solr/ext-solr/pull/4573>`_
*   [TASK] Simplify unit tests configuration by @dkd-kaehm in `#4571 <https://github.com/TYPO3-Solr/ext-solr/pull/4571>`_
*   [TASK] Remove unused TYPO3 Core context initialization in integration tests by @sfroemkenjw in `#4568 <https://github.com/TYPO3-Solr/ext-solr/pull/4568>`_
*   [TASK] Update test extension path in IntegrationTestBase by @sfroemkenjw in `#4567 <https://github.com/TYPO3-Solr/ext-solr/pull/4567>`_
*   [TASK] Refactor DI handling for FrequentSearchesService by @sfroemkenjw in `#4548 <https://github.com/TYPO3-Solr/ext-solr/pull/4548>`_
*   [TASK] Refactor GroupItemPaginateViewHelper by @sfroemkenjw in `#4549 <https://github.com/TYPO3-Solr/ext-solr/pull/4549>`_
*   [BUGFIX] Adapt tests by @bmack in `#4546 <https://github.com/TYPO3-Solr/ext-solr/pull/4546>`_
*   [TASK] Remove PSR-14 event, and update RST file by @bmack in `#4546 <https://github.com/TYPO3-Solr/ext-solr/pull/4546>`_
*   [!!!][TASK] Remove site hash strategy flag by @bmack in `#4546 <https://github.com/TYPO3-Solr/ext-solr/pull/4546>`_
*   [BUGFIX] Polish infobox to align with current ContextualFeedbackSeverity by @amirarends in `#4551 <https://github.com/TYPO3-Solr/ext-solr/pull/4551>`_
*   [TASK] Prepare v14 release notes by @dkd-friedrich in `#4547 <https://github.com/TYPO3-Solr/ext-solr/pull/4547>`_
*   [BUGFIX] Remove TSFE from access component by @garfieldius in `#4544 <https://github.com/TYPO3-Solr/ext-solr/pull/4544>`_
*   [BUGFIX] Allow GroupItemPaginateViewHelper template to be overridden by @jschlier in `#4542 <https://github.com/TYPO3-Solr/ext-solr/pull/4542>`_
*   [TASK] Replace removed FormResultCompiler with FormResultFactory for TYPO3 14 by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix access protected content indexing for TYPO3 14 by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Speed-up integration tests by skipping database initialization by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix integration tests for TYPO3 14 compatibility by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Make Item properties non-nullable with strict validation by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix FlexForm handling in SettingsPreviewOnPlugins for TYPO3 14 by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix TCA searchFields deprecation and ContentObjectRenderer for TYPO3 14 by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Refactor FrontendEnvironment/Tsfe to FrontendSimulation/FrontendAwareEnvironment by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix ViewHelper classes for TYPO3 14 / Fluid v5 compatibility by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Fix Report classes for TYPO3 14 compatibility by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Upgrade deps for TYPO3 14 by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Prepare schema/configset for dev-14.0.x by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [TASK] Remove ext_econf.php file by @dkd-kaehm in `#4528 <https://github.com/TYPO3-Solr/ext-solr/pull/4528>`_
*   [BUGFIX] Respect plugin TS in RelevanceComponent by @helhum in `#4532 <https://github.com/TYPO3-Solr/ext-solr/pull/4532>`_
*   [BUGFIX] Catch InvalidArgumentException for missing site languages in GarbageHandler by @mikelwohlschlegel in `#4534 <https://github.com/TYPO3-Solr/ext-solr/pull/4534>`_
*   [BUGFIX] Add headers palette to solr plugin CType TCA definitions by @dkd-kaehm in `#4536 <https://github.com/TYPO3-Solr/ext-solr/pull/4536>`_
*   [BUGFIX] CS issues 2026.02.05 by @dkd-kaehm in `#4526 <https://github.com/TYPO3-Solr/ext-solr/pull/4526>`_
*   [DOCS] Update version matrix by @dkd-friedrich in `#4518 <https://github.com/TYPO3-Solr/ext-solr/pull/4518>`_
*   [SECURITY] Update to Apache Solr 9.10.1 by @dkd-friedrich in `#4518 <https://github.com/TYPO3-Solr/ext-solr/pull/4518>`_
*   [DOCS] Update version matrix in main for current versions by @dkd-kaehm in `91c455b8a <https://github.com/TYPO3-Solr/ext-solr/commit/91c455b8a015c89e4222ba6dd7a76984d303b406>`_


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

- `Amir Arends <https://github.com/amirarends>`_
- `Andreas Häfner <https://github.com/un3us>`_
- `Anton Danilov <https://github.com/danilovq>`_
- `@BastiLu <https://github.com/BastiLu>`_
- `@beardcoder <https://github.com/beardcoder>`_
- `Benni Mack <https://github.com/bmack>`_
- `Bernd Wilke <https://github.com/pi-phi>`_
- `@daylightsoftware <https://github.com/daylightsoftware>`_
- `Dmitry Dulepov <https://github.com/dmitryd>`_
- `Florian Lehnebach <https://github.com/dkd-lehnebach>`_
- `@garfieldius <https://github.com/garfieldius>`_
- `@hdj-typoconsult <https://github.com/hdj-typoconsult>`_
- `Helmut Hummel <https://github.com/helhum>`_
- `@hnadler <https://github.com/hnadler>`_
- `@jschlier <https://github.com/jschlier>`_
- `Konrad Michalik <https://github.com/konradmichalik>`_
- `Marcus Schwemer <https://github.com/mschwemer>`_
- `Markus Friedrich <https://github.com/dkd-friedrich>`_
- `Mikel Wohlschlegel <https://github.com/mikelwohlschlegel>`_
- `Oliver Hauser <https://github.com/dkd-hauser>`_
- `Olivier Dobberkau <https://github.com/dkd-dobberkau>`_
- `Philipp Kitzberger <https://github.com/kitzberger>`_
- `Rafael Kähm <https://github.com/dkd-kaehm>`_
- `Sascha Nowak <https://github.com/saschanowak>`_
- `Stefan Frömken <https://github.com/sfroemkenjw>`_
- `@tillhoerner <https://github.com/tillhoerner>`_
- `Tobias Gaertner <https://github.com/tgaertner>`_
- `Tobias Gülzow <https://github.com/guelzow>`_
- `Wolfgang Klinger <https://github.com/wazum>`_

Also a big thank you to our partners who have already concluded one of our new development participation packages such
as Apache Solr EB for TYPO3 14 LTS (Feature):

*   CS2 AG
*   digit.ly
*   fixpunkt werbeagentur gmbh
*   in2code GmbH
*   L.N. Schaffrath DigitalMedien GmbH
*   LOUIS INTERNET GmbH
*   queo GmbH
*   toco3 GmbH & Co. KG
*   Umweltbundesamt GmbH
*   Universität für Musik und darstellende Kunst Wien
*   Universität Regensburg


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
