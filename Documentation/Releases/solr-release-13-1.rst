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


Contributors
============

Like always this release would not have been possible without the help from our
awesome community. Here are the contributors to this release.

(patches, comments, bug reports, reviews, ... in alphabetical order)

- TBD

Also a big thank you to our partners who have already concluded one of our new development participation packages such
as Apache Solr EB for TYPO3 13 LTS (Feature):

* TBD

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
