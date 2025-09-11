.. _appendix-known-issues:

Appendix - Known issues
=======================

Text embedding failure
----------------------

EXT:solr 12.1 and 13.1 introduced an initial vector search option, but further improvements and features are planned and in progress.

A weak point of the vector search feature is the error handling, if the required llm is not defined or the embedding fails, indexing and/or search will be impaired.

Indexing
~~~~~~~~

If the vectors couldn't be calculated during indexing, the index process won't fail, but the documents will miss the vectors and cannot be found.

Solr status is fine, but the `TextToVectorUpdateProcessor` reports "Could not vectorise".

Search
~~~~~~

The search term has also to be vectorized to perform a search in the frontend, missing or unavailable large language models will currently lead to a `SolrInternalServerErrorException`.

Apache Solr returns HTTP status 500 and details about the exception are available, but differ depending on the model used.
