=================
Index Maintenance
=================

Solr offers a lot of request handlers to do maintenance tasks.

Committing pending documents
============================

|

.. code-block:: bash

    curl http://host:port/solr-path/update -H "Content-Type: text/xml"
        --data-binary '<commit />'

|

Clearing the index
==================


|

.. code-block:: bash

    curl http://host:port/solr-path/update -H "Content-Type: text/xml"
        --data-binary '<delete><query>*:*</query></delete>'

    curl http://host:port/solr-path/update -H "Content-Type: text/xml"
        --data-binary '<commit />'

|

Optimizing the index
====================

You should do this every once in a while, f.e. every day. For TYPO3, there is already a scheduler task available for this.

|

.. code-block:: bash

    curl http://host:port/solr-path/update -H "Content-Type: text/xml"
        --data-binary '<optimize />'

|

Searching the index from the command line
=========================================

Parameters:

:q: what to search for. Format: fieldName:fieldValue
:qt: defines the query type, for the command line we recommend "standard", the extension itself uses "dismax"
:fl: comma separated list of fields to return
:rows: number of rows to return
:start: offset from where to return results

|

.. code-block:: bash

    curl 'http://host:port/path-to-solr/select?q=hello&qt=standard&fl=title,content'

|

Getting information / statistics about the index
================================================

|

.. code-block:: bash

    curl 'http://host:port/path-to-solr/admin/luke'

|

Initializing the spell checker
==============================

You only need to do this once. Once the spell checker index is initialized it gets updated automatically.

|

.. code-block:: bash

    curl 'http://host:port/path-to-solr/select/?spellcheck=true&spellcheck.build=true'

|

Alternatively, since version version:1.2 the spell checker index is built automatically when you run an optimize command on your index. The extension comes with an Optimze Index Task for the scheduler. We recommend to set it up to run once a day.

