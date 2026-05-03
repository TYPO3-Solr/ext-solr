.. _backend-module:

Backend Modules
===============

The backend modules live in the *Apache Solr* section and can be unlocked for backend users or groups individually. Each module focuses on a specific maintenance or inspection task and is rendered with the modernised TYPO3 v14 / Bootstrap 5 panels, callouts and badges that ship with the extension.

.. image:: /Images/Backend/solr-backend_modules-listing.png

The screenshots below were captured against the demo site *TYPO3 Vienna Camp 2026* on TYPO3 14.3 with four configured cores (``core_en``, ``core_de``, ``core_zh``, ``core_hu``).

Info Module
-----------

The **Info** module is the read-only health dashboard. It groups everything you typically need when answering "is Solr reachable, what's in the index, and what's broken" into five tabs:

#. :ref:`Übersicht (Overview) <backend-module-info-overview>` — connections + Solr server runtime
#. :ref:`Statistiken (Statistics) <backend-module-info-statistics>` — top search phrases and queries-over-time chart
#. :ref:`Indexfelder (Index Fields) <backend-module-info-indexfields>` — Solr schema field listing per core
#. :ref:`Indexdokumente (Index Documents) <backend-module-info-indexdocuments>` — indexed documents per core, scoped to the selected page
#. :ref:`Berichte (Reports) <backend-module-info-reports>` — compact status checks plus the production checklist

.. _backend-module-info-overview:

Übersicht (Overview)
^^^^^^^^^^^^^^^^^^^^

The *Übersicht* tab is the entry point of the Info module. It shows:

* a "live status" callout with a *Status aktualisieren* button so reports re-run on demand after restarting or reconfiguring Solr;
* the **verwendete Domain** (the domain TYPO3 sends to Solr to scope documents per site) and the site **API-Schlüssel** (the SiteHash);
* a **Solr-Cores** card per configured language core, each labelled with its language, ``coreName``, currently selected-site document count, and connection state (*Verbunden* / *Nicht verbunden*);
* the **TYPO3-Endpunkt** that TYPO3 itself uses, together with the **Admin-Oberfläche** links (HTTPS :8984 and HTTP :8983 in the demo) so you can jump into Solr's own admin UI;
* a **Solr-Serverübersicht** below with the loaded core count, total document count, Solr/Lucene/Java versions, JVM heap, and the Solr home path.

.. image:: /Images/Backend/solr-backend-module_Info-Connections.png

.. _backend-module-info-statistics:

Statistiken (Statistics)
^^^^^^^^^^^^^^^^^^^^^^^^

The *Statistiken* tab visualises search activity recorded by EXT:solr's statistics logger. The default range is the last 30 days, narrow it via the *Start* / *Ende* date pickers and the *Filter* button.

* The **Suchanfragen-over-time** chart at the top plots query volume per day.
* **Top 5 Suchbegriffe** lists the most frequent search phrases with hit counts.
* **Top 5 Suchbegriffe ohne Treffer** highlights the most-asked queries that returned zero documents — the prime hint list for synonym, stop-word, or content gaps.
* **Suchbegriff-Statistiken** is the full ranked table with phrase, query count, average result count and percentage share.

.. image:: /Images/Backend/solr-backend-module_Info-Statistics.png

.. _backend-module-info-indexfields:

Indexfelder (Index Fields)
^^^^^^^^^^^^^^^^^^^^^^^^^^

The *Indexfelder* tab inspects the live Solr schema for every connected core. For each core (``/core_en``, ``/core_de`` …) the module shows:

* three KPI cards — **Dokumente** (indexed documents in this core), **Gelöschte Dokumente** (deleted/tombstoned documents waiting to be merged out), and **Felder** (total schema fields);
* a sortable table with **Feldname**, **Indexfeldtyp** (``string``, ``text``, ``date``, ``long``, ``integer`` …), **Dokumente** containing the field, and **Eindeutige Begriffe** (unique terms indexed in that field).

Use this tab to verify that custom indexers actually populate the fields you configured and that token counts make sense.

.. image:: /Images/Backend/solr-backend-module_Info-IndexFields.png

.. _backend-module-info-indexdocuments:

Indexdokumente (Index Documents)
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

The *Indexdokumente* tab drills into the actual records currently in Solr. Three scoped totals sit at the top of the panel:

* **Seite** — documents indexed from the page selected in the page tree.
* **Site gesamt** — documents from the whole site under that page tree root.
* **Core gesamt** — the unfiltered document count Solr reports for the core (may include documents from other sites sharing the core).

Below that, every core is listed with the same trio plus a per-type breakdown (``pages``, ``tx_news_domain_model_news`` …). Each row shows ID, title, URL, *Erstellt* / *Indexiert* timestamps, and a quick action button to view or re-index the single document.

.. image:: /Images/Backend/solr-backend-module_Info-IndexDocuments.png

.. _backend-module-info-reports:

Berichte (Reports)
^^^^^^^^^^^^^^^^^^

The *Berichte* tab is the compact, in-module version of the system-wide TYPO3 reports module. It is split into two cards:

* **Solr-Berichte** — every status check the extension contributes (Access Filter Plugin, Solr-Konfiguration, Verbindungsstatus, Schemaversion, Site-Handling-Status, Vector Support, Solr-Version, Solrconfig-Version, URL-fähige fopen-Wrapper). Each row collapses by default; only checks that report *Prüfen* (warning) or *Problem* (error) auto-expand. The toggle on the right is sized for *888 Einträge* so all rows align regardless of the count, and it right-aligns the count text next to the chevron.
* **Produktions-Checkliste** — operational checks that TYPO3 can't verify automatically (for example "TYPO3-14-Configset", "JVM-Speicher", "Öffentlicher Zugriff", "Scheduler-Task ‚Index Queue Worker'"). Each entry carries a short description so reviewers know what the check is asserting before opening the card.

.. image:: /Images/Backend/solr-backend-module_Info-Reports.png

Core Optimization
-----------------

The **Core-Optimierung** module manages the language-specific tuning files Solr uses to influence ranking and matching. Pick the target core via the dropdown in the docheader; the same module exposes two tabs.

.. _backend-module-stopword:

Stop Words (Stoppwörter)
^^^^^^^^^^^^^^^^^^^^^^^^

The *Stoppwörter* tab manages words that Solr should ignore both at index and query time. Common cases:

* very frequent function words (``the``, ``and``, ``der``, ``die`` …) — they dilute the score and cost recall;
* domain-specific noise words you never want to match.

The textarea is the canonical list (one word per line). Use *Stoppwörter speichern* to push the list to Solr for the selected core, *Liste exportieren* to download it, and the upload form below to replace the list from a plain-text file.

.. image:: /Images/Backend/solr-backend-module_CoreOptimization-StopWords.png

.. _backend-module-synonyms:

Synonyms (Synonyme)
^^^^^^^^^^^^^^^^^^^

The *Synonyme* tab lets users find documents that don't literally contain the search term but a related one (``smartphone`` ↔ ``cellphone``, ``mobile``, ``mobilephone``).

.. important::

   The original term must appear on **both** sides of the mapping, otherwise the search for the term itself will stop matching.

   ``smartphone`` → ``smartphone, cellphone, mobile, mobilephone``

The form on the left adds a single mapping (*Basiswort* + comma-separated *Synonyme*); the panel on the right uploads a Solr-format synonym list — you choose between merging into the existing list or replacing it entirely. After adding or uploading, re-index the affected content so documents are matched with the new mapping.

.. image:: /Images/Backend/solr-backend-module_CoreOptimization-Synonyms.png

Index Queue
-----------

The **Index-Queue** module is the operational hub for indexing. Its responsibilities:

* show the current **Status der Index-Queue** — a labelled progress bar split into *Indexiert* / *Ausstehend* / *Fehler* with a numeric badge for each (in the screenshot the queue holds ``25/25`` indexed and ``25/25`` pending items, with no errors);
* let you initialise the queue per indexing configuration (``pages``, ``news`` …) using the **Index-Queue initialisieren** card — selecting a row and clicking *Ausgewählte Inhalte in die Queue stellen* triggers a full re-queue; the *Index Queue Worker* scheduler task drains it afterwards;
* trigger ad-hoc indexing without waiting for the scheduler — see the next subsection;
* clear the queue for the current site via **Index-Queue leeren** when you want to start over.

.. image:: /Images/Backend/solr-backend-module_IndexQueue.png

Manual indexing actions
^^^^^^^^^^^^^^^^^^^^^^^

The status section offers two buttons for ad-hoc indexing without waiting for the scheduler:

* **Einmal indexieren** processes a single pending item per click. Useful for spot-checking indexer changes against one document.
* **N indexieren** appears whenever more than one item is pending. ``N`` is rendered dynamically as ``min(pendingCount, 50)`` so the label reflects exactly how many items the next click will process — never more than the queue holds, and capped at 50 per request to keep the HTTP round-trip predictable. With 25 pending items the button reads "25 indexieren"; with 200 pending it reads "50 indexieren" and you click it again to drain the next batch.

Both actions reuse :php:`IndexService::indexItems()`, so the per-item events, error handling, and Solr commit behavior are identical to a scheduler run.

Index Administration (Index-Verwaltung)
---------------------------------------

The **Index-Verwaltung** module groups the destructive operations that affect the live index in three explicit cards. Each card carries a description so the consequences are visible *before* clicking.

* **Core-Konfiguration neu laden** — reloads the Solr cores attached to the selected site after changes to synonyms, stop-words, protected words, or other core/index configuration.
* **Index leeren** — removes only the documents of the selected site from Solr, even if multiple sites share a core. Safer than wiping the whole core.
* **Index-Queue leeren** — empties the TYPO3-side queue rows for the selected site. After clearing, re-initialise the queue (see the *Index Queue* module) so indexing can resume.

The danger cards are border-highlighted in TYPO3's danger token, and submitting them opens a TYPO3 confirmation modal — there is no single-click destructive action.

.. image:: /Images/Backend/solr-backend-module_IndexAdministration.png

Solr LLM
--------

The **Solr LLM** module is the bridge between EXT:solr and a provider-agnostic ``EXT:nr_llm`` configuration. It does not host the provider credentials itself — those stay in ``nr_llm`` — but it is where you pick the configuration that the Solr search uses, verify that it works, and watch how often it is invoked.

The module pre-selects the configuration referenced in ``plugin.tx_solr.search.llmQueryEnhancer.configurationIdentifier`` (default: ``solr-search-query-enhancer``). Use the dropdown in the toolbar to switch to a different configuration, *Konfiguration anzeigen* to reload the panel for the chosen one, and *Provider testen* to fire a live test call against the provider. The two right-aligned buttons (*LLM-Konfigurationen verwalten*, *Einrichtungsassistent öffnen*) jump straight into the corresponding ``EXT:nr_llm`` modules.

.. image:: /Images/Backend/solr-backend-module_Llm.png

The body is organised as three status cards followed by two reference cards:

* **Query-Optimierung zur Laufzeit** — shows whether the runtime enhancer is currently active for the selected site. The badge reads *Aktiv* when ``plugin.tx_solr.search.llmQueryEnhancer.enabled = 1`` and *Pausiert* otherwise. The card lists the configuration identifier in use and the cache lifetime (``cacheLifetime`` in seconds, default ``86400``) so you know how long an enhanced query is reused before it is recomputed.
* **Provider-Rückmeldung** — summarises the picked ``nr_llm`` configuration: provider + adapter type, model name and id, and the per-call limits (*Temperatur*, *max. Tokens*). The badge is *Konfiguriert* when the configuration is active, *Inaktiv* when it exists but is disabled, and *Fehlt* when the identifier cannot be resolved.
* **Nutzungsstatistik** — three metric tiles (*Anfragen*, *Tokens*, *geschätzte Kosten*) plus the timestamp of the last tracked call. Numbers are read from ``EXT:nr_llm``'s usage log so they are scoped to that configuration, not the whole site.

.. important::

   The badge on *Query-Optimierung zur Laufzeit* reflects the runtime flag only. A configuration can be *Konfiguriert* and the runtime can still be *Pausiert* — flip the TypoScript switch to actually use it on the frontend:

   .. code-block:: typoscript

      plugin.tx_solr.search.llmQueryEnhancer.enabled = 1
      plugin.tx_solr.search.llmQueryEnhancer.configurationIdentifier = solr-search-query-enhancer

* **Wo das LLM konfiguriert wird** — three numbered steps (provider/model in ``nr_llm``, an active configuration with the matching identifier, the TypoScript snippet above) plus the snippet itself, ready to copy.
* **Was wird mit LLM indexiert?** — clarifies what the current ``solr-search-query-enhancer`` setup actually does: it rewrites or expands the **query** at runtime, it does **not** write extra LLM fields into Solr documents. Vector indexing is a separate path that only runs when the Solr text-to-vector model store is configured. LLM call counts come from ``nr_llm``'s usage log; document counts continue to come from Solr.

If ``EXT:nr_llm`` is not installed or not active the module collapses to a single empty-state panel telling you to install it — none of the cards above are rendered, because they would have nothing to show.
