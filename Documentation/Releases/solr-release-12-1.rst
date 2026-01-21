.. _releases-12-1:

=============
Releases 12.1
=============

..  include:: HintAboutOutdatedChangelog.rst.txt

Release 12.1.1
==============

This is a security release for TYPO3 12 LTS.

!!! Upgrade to Apache Solr 9.10.1
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Apache Solr 9.10.1 fixes several security issues, please upgrade your Apache Solr instance!

*   CVE-2025-54988: Apache Solr extraction module vulnerable to XXE attacks via XFA content in PDFs
*   CVE-2026-22444: Apache Solr: Insufficient file-access checking in standalone core-creation requests
*   CVE-2026-22022: Apache Solr: Unauthorized bypass of certain "predefined permission" rules in the RuleBasedAuthorizationPlugin


All Changes
-----------

*   [DOCS] Update version matrix in main for current versions by @dkd-kaehm in `#4506 <https://github.com/TYPO3-Solr/ext-solr/pull/4506>`_
*   [SECURITY] Update to Apache Solr 9.10.1 by @dkd-friedrich in `#4517 <https://github.com/TYPO3-Solr/ext-solr/pull/4517>`_

Release 12.1.0
==============

We are happy to release EXT:solr 12.1.0.
The focus of this release has been on AI integrations.

New in this release
-------------------

Initial vector search
~~~~~~~~~~~~~~~~~~~~~

In 12.1 and 13.1 a first step towards vector and AI support has been taken, focusing on enhancing search capabilities through vector search technology.
This feature allows more sophisticated and semantically enriched search functionalities by utilizing vector representation of text data.

The current vector integration is very initial and intended as a starting point. We encourage users to test this feature and provide feedback to help improve its further development.

Key Highlights
""""""""""""""

1. **Initial Vector Search Introduction:**

   - The EXT:solr version 12.1 and 13.1 introduces an initial vector search option as a new search variant.
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

For more detailed technical implementation and setup instructions, users should refer to the version 12.1 or 13.1 release notes and the associated documentation sections.

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

*   [FEATURE] Add DenseVectorField in schemas by @dkd-kaehm in `#4440 <https://github.com/TYPO3-Solr/ext-solr/pull/4440>`_
*   [TASK] Prepare release-12.1.x branch by @dkd-kaehm in `#4445 <https://github.com/TYPO3-Solr/ext-solr/pull/4445>`_
*   [TASK] 12.1.x-dev Update solarium/solarium requirement from 6.3.7 to 6.4.1 by @dependabot[bot] in `#4434 <https://github.com/TYPO3-Solr/ext-solr/pull/4434>`_
*   [FEATURE] Initial vector search by @dkd-friedrich in `#4447 <https://github.com/TYPO3-Solr/ext-solr/pull/4447>`_
*   [TASK] 12.1.x-dev Bump solr from 9.9.0 to 9.10.0 in /Docker/SolrServer by @dependabot[bot] in `#4463 <https://github.com/TYPO3-Solr/ext-solr/pull/4463>`_
*   Fix bug for phrase search with slops, bigram and trigram by Florian Rival in `#4472 <https://github.com/TYPO3-Solr/ext-solr/pull/4472>`_
*   [BUGFIX] Pass TypoScript configuration to SolrWriteService by @dkd-friedrich in `#4475 <https://github.com/TYPO3-Solr/ext-solr/pull/4475>`_
*   [FEATURE] Add dateRange field type in schema by @tillhoerner in `#4487 <https://github.com/TYPO3-Solr/ext-solr/pull/4487>`_
*   [BUGFIX] Replace TSFE call for page type by Sebastian Klein in `#4488 <https://github.com/TYPO3-Solr/ext-solr/pull/4488>`_
*   [FEATURE] Improve BeforeSearchFormIsShownEvent by Simon Schaufelberger in `#4486 <https://github.com/TYPO3-Solr/ext-solr/pull/4486>`_
*   [FEATURE] Add HEALTHCHECK to Dockerfile by @dkd-kaehm in `#4489 <https://github.com/TYPO3-Solr/ext-solr/pull/4489>`_
*   !!![FEATURE] allow nested TypoScript on multiValue fields by @dkd-kaehm in `#4496 <https://github.com/TYPO3-Solr/ext-solr/pull/4496>`_


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

*   `Markus Friedrich <https://github.com/dkd-friedrich>`_
*   `Rafael Kähm <https://github.com/dkd-kaehm>`_


Also a big thank you to our partners who have already concluded one of our new development participation packages such
as Apache Solr EB for TYPO3 12 LTS (Maintenance):

*   3m5. Media GmbH
*   ACO Ahlmann SE & Co. KG
*   AmedickSommer Neue Medien GmbH
*   CDG 59
*   chiliSCHARF GmbH
*   Columbus Interactive GmbH
*   cosmoblonde GmbH
*   CPS GmbH
*   Davitec GmbH
*   Deutsches Literaturarchiv Marbach
*   Die Medialen GmbH
*   Digitale Offensive GmbH
*   Eidg. Forschungsanstalt WSL
*   GAYA
*   Gernot Leitgab
*   grips IT GmbH
*   Gyldendal A/S
*   HSPV NRW
*   INOTEC Sicherheitstechnik GmbH
*   Intersim AG
*   Kassenzahnärztliche Vereinigung Bayerns (KZVB)
*   La Financière agricole du Québec
*   Landesinstitut für Schule und Medien Berlin-Brandenburg
*   Landeskriminalamt Thüringen
*   Lingner Consulting New Media GmbH
*   LST AG
*   medien.de mde GmbH
*   MEDIENHAUS der Evangelischen Kirche in Hessen und Nassau GmbH
*   mellowmessage GmbH
*   NEW.EGO GmbH
*   OST Ostschweizer Fachhochschule
*   Provitex GmbH Provitex GmbH
*   Randstad Digital
*   rms. relationship marketing solutions GmbH
*   Serviceplan Suisse AG
*   sgalinski Internet Services
*   Stratis
*   Studio 9 GmbH
*   SUNZINET GmbH
*   Webtech AG
*   Werbeagentur netzpepper
*   zimmer7 GmbH



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
