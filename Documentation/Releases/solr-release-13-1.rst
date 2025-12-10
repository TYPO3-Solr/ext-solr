.. _releases-13-1:

=============
Releases 13.1
=============

..  include:: HintAboutOutdatedChangelog.rst.txt


Release 13.1.0
==============

We are happy to release EXT:solr 13.1.0.
The focus of this release has been on AI integrations.


New in this release
-------------------

Initial vector search
~~~~~~~~~~~~~~~~~~~~~

In 13.1 a first step towards vector and AI support has been taken, focusing on enhancing search capabilities through vector search technology.
This feature allows more sophisticated and semantically enriched search functionalities by utilizing vector representation of text data.

The current vector integration is very initial and intended as a starting point. We encourage users to test this feature and provide feedback to help improve its further development.

Key Highlights
""""""""""""""

1. **Initial Vector Search Introduction:**

   - The EXT:solr version 13.1 introduces an initial vector search option as a new search variant.
   - Activating this feature automatically generates vectors during indexing and frontend search.
   - A connected large language model (LLM) is required for operation, though it is not directly related to EXT:solr.

2. **Handling and Limitations of Vector Search:**

   - Current implementation includes limitations, especially in error handling when required LLMs are not defined, leading to potential impairments in indexing or search.
   - Indexing without vector calculation results in documents not being found despite successful index status.
   - A missing or unavailable LLM during search attempts can lead to a `SolrInternalServerErrorException`, returning an HTTP status 500.

3. **Configuring a Large Language Model:**

   - To use vector search, a large language model must be connected to encode text into vectors.
   - Configuration details are available in the Apache Solr Reference Guide 9.9.
   - Models can be uploaded using a JSON file and cURL command.

     ..  code-block:: json
         :caption: Example configuration for the JSON file

         {
           "class": "dev.langchain4j.model.openai.OpenAiEmbeddingModel",
           "name": "llm",
           "params": {
             "baseUrl": "https://api.openai.com/v1",
             "apiKey": "apiKey-openAI",
             "modelName": "text-embedding-3-small",
             "timeout": 5,
             "logRequests": true,
             "logResponses": true,
             "maxRetries": 2
           }
         }
   - The number of dimensions for vectors defaults to 768 but can be adjusted via the `SOLR_VECTOR_DIMENSION` environment variable.


Future Developments
"""""""""""""""""""

- **Improved Error Handling:**
  Future versions plan to enhance error handling during vector indexing and search to increase robustness and reliability.

- **Additional Query Types:**
  New query types such as vector sorting and vector re-ranking are planned, allowing for more advanced search result manipulation.

- **Backend Module for LLM Management:**
  A backend module for managing large language models is anticipated, simplifying maintenance and configuration for developers.

This introduction marks a significant advancement for TYPO3's search capabilities by integrating AI technologies, with ongoing improvements and features planned for future releases.

For more detailed technical implementation and setup instructions, users should refer to the version 13.1 release notes and the associated documentation sections.

Technical insights
""""""""""""""""""

As soon as vector search is enabled, EXT:solr will use the connected LLM to generate vectors during indexing and for each search in the frontend. During indexing vectors are generated based on field
`vectorContent` which is by default filled with the contents of the `content` field. TypoScript indexing configurations can be used to customize the contents of the `vectorContent` field, e.g.:

..  code-block:: typoscript
    :caption: How to define the contents of the vector field

    plugin.tx_solr.index.queue.news.fields {
      vectorContent = SOLR_CONTENT
      vectorContent.cObject = COA
      vectorContent.cObject {
        10 = TEXT
        10 {
          field = name
        }

        15 = TEXT
        15 {
          field = bodytext
        }
      }
    }

During indexing vectors will be created and stored in field `vector`.

..  tip::
    `vector` and `vectorContent` are not `stored` and thus not included in the search results, but for debugging purposes, it may be helpful to set to `stored="true"` to verify the stored content.

!!! Upgrade to Apache Solr 9.10.0+
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

This release requires Apache Solr at least v9.10.0.

!!! Allow nested TypoScript on multiValue fields
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

This breaking change allows nested TypoScript index configurations for multi-value/array fields like:

..  code-block:: typoscript
    :caption: How to define the contents of the vector field

    plugin.tx_solr.index.queue.pages.fields.someDoktypeSpecificCategory_stringM = CASE
    plugin.tx_solr.index.queue.pages.fields.someDoktypeSpecificCategory_stringM {
      key.field = doktype
      80 = SOLR_RELATION
      80 {
        localField = some_doktype_specific_sys_category
        multiValue = 1
      }
    }

This feature removes the SerializedValueDetector hook without any replacements, due of `new TypoScript parser in Frontend on TYPO3 12 <https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Breaking-97816-NewTypoScriptParserInFrontend.html>`_,
which does not require any manual stdWrap by EXT:solr.
Each custom cObect implementation returning the array/object as PHP serialized string will be used without registration or check.
Note: Empty arrays/objects will not be written to the documents.
Check if your system uses the SerializedValueDetector hook :php:`$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['detectSerializedValue']`
remove it and check the desired fields are properly indexed.


All Changes
-----------

*   [FEATURE] Add DenseVectorField in schemas by @dkd-kaehm in `#4439 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [TASK] Prepare branch for 13.1.x versions by @dkd-kaehm in `#4443 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [TASK] 13.0.x-dev Update solarium/solarium requirement from 6.3.7 to 6.4.1 by @dependabot[bot] in `#4435 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [BUGFIX] don't use pages uid 0 via l10n_parent by @dkd-kaehm in `#4449 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   Remove OpenSearch profile link by @infabo in `#4418 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [FEATURE] Initial vector search by @dkd-friedrich in `#4446 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [FEATURE] Cascade fe_group changes with extendToSubpages (reindex + cleanup) by @DavRet in `#4400 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [TASK] Switch dependabot to supported branches: 13.1.x and 12.1.x by @dkd-kaehm in `#4454 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [BUGFIX] pass a request with page id to Configuration manager by @WebsiteDeveloper in `#4452 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [BUGFIX] Initialize the localRootLine property before usage by @davidlemaitre in `#4423 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   Adjust resource identifier in PageRenderer asset registration in backend module template by @chrrynobaka in `#4386 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [TASK] 13.1.x-dev Bump solr from 9.9.0 to 9.10.0 in /Docker/SolrServer by @dependabot[bot] in `#4462 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   Fix bug for phrase, bigramPhrase and trigramPhrase searches with slops by @Oktopuce in `#4460 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [BUGFIX] Describe array shape of findTranslationOverlaysByPageId correctly by @smichaelsen in `#4482 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [DOCS] Mention rootline for __pageSections to work by @kitzberger in `#4478 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   Update ConfigureRouting.rst by @simonduerr in `#4477 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [FEATURE] Add dateRange field type in schema by @tillhoerner in `#4461 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [BUGFIX] Replace TSFE call for page type by @sebkln in `#4458 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [FEATURE] Improve BeforeSearchFormIsShownEvent by @simonschaufi in `#4481 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [TASK] Replace md5/sha1 calls with hash method by @thomashohn in `#4437 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [FEATURE] Add HEALTHCHECK to Dockerfile by @dkd-kaehm in `#4484 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [BUGFIX] Respect site configuration when resolving page ID for TSFE initialization by @sfroemkenjw in `#4421 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [TASK] Improve vector search documentation by @dkd-friedrich in `#4491 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [BUGFIX] PHP Warning: Trying to access array offset on value of type null by @kitzberger in `#4330 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [BUGFIX] Check if facet value is set by @spoonerWeb in `#4493 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   [BUGFIX] Check if variable is set and string by @spoonerWeb in `#4495 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_
*   !!![FEATURE] allow nested TypoScript on multiValue fields by @dkd-kaehm in `#4485 <https://github.com/TYPO3-Solr/ext-solr/pull/4439>`_


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

- Achim Fritz
- Albrecht Köhnlein
- Alexander Nitsche
- Andreas Kießling
- André Buchmann
- Bastien Lutz
- Benni Mack
- Benoit Chenu
- Christoph Lehmann
- @chrrynobaka
- Daniel Siepmann
- `@derMatze82 <https://github.com/derMatze82>`_
- Dmitry Dulepov
- Elias Häußler
- Eric Chavaillaz
- Ernesto Baschny
- Fabio Norbutat
- Felix Ranesberger
- ferfrost
- Florian Rival
- Georg Ringer
- Harald Witt
- `Hendrik vom Lehn <https://github.com/hvomlehn-sds>`_
- `@hnadler <https://github.com/hnadler>`_
- Henrik Elsner
- Ingo Fabbri
- Jennifer Geiß
- Julian Hofmann
- Kai Lochbaum
- Lars Tode
- Lukas Niestroj
- Marc Hirdes
- Mario Lubenka
- `Markus Friedrich <https://github.com/dkd-friedrich>`_
- Matthias Vogel
- `@n3amil / Cypelt <https://github.com/n3amil>`_
- Oliver Bartsch
- Patrick Schriner
- Philipp Kitzberger
- Pierrick Caillon
- `Rafael Kähm <https://github.com/dkd-kaehm>`_
- René Maas
- Roman Schilter
- Sascha Nowak
- Sascha Schieferdecker
- Sebastian Schreiber
- Silvia Bigler
- Søren Malling
- Stefan Frömken
- Steve Lenz
- Stämpfli Kommunikation
- Sven Erens
- Sven Teuber
- Thomas Löffler
- Till Hörner
- Tim Dreier
- Tobias Hövelborn
- Tobias Schmidt
- Torben Hansen
- `@twojtylak <https://github.com/twojtylak>`_
- Wolfgang Wagner | wow! solution

Also a big thank you to our partners who have already concluded one of our new development participation packages such
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
