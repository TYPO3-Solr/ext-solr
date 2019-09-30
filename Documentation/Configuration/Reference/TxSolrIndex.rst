.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../../Includes.txt


.. _conf-tx-solr-index:

tx_solr.index
===============

This part contains all configuration that is required to setup your indexing configuration. You can use EXT:solr
to easily index pages or any kind of records of your TYPO3 CMS.

.. contents::
    :local:

Allows to prevent frontend indexing of pages when a backend editor is logged in and browsing the website.

additionalFields (deprecated)
-----------------------------

:Type: String, cObject (since 1.1)
:TS Path: plugin.tx_solr.index.additionalFields
:Since: 1.0
:Deprecated: 2.0

A mapping of Solr field names to additional string values to be indexed with page documents. Use dynamic fields to index additional data, this way you don't have to modify the schema.xml

Example:

.. code-block:: typoscript

    plugin.tx_solr.index.additionalFields {
      myFirstAdditionalField_stringS = some string

      mySecondAdditionalField_stringS = TEXT
      mySecondAdditionalField_stringS {
        value = some other value that can be constructed using any TypoScript cObject
        case = upper
        // more processing here as needed
      }
    }

Since version 1.1 you can use cObjects to generate the value for the field. The only thing to observe is that you generate strings. Other values may work, but haven't been tested yet.

Deprecated since 2.0, please use the Index Queue indexing configurations instead as it allows you to define more precisely for which types of documents you want which fields to be indexed.


fieldProcessingInstructions
---------------------------

:Type: cObject
:TS Path: plugin.tx_solr.index.fieldProcessingInstructions
:Since: 1.2 2.0
:Options: timestampToIsoDate, uppercase, pathToHierarchy (2.5-dkd), pageUidToHierarchy (2.5-dkd)

Assigns processing instructions to Solr fields during indexing (Syntax: Solr index field = processing instruction name). Currently it is not possible to extend / add own processing instructions.
Before documents are sent to the Solr server they are processed by the field processor service. Currently you can make a filed's value all uppercase, convert a UNIX timestamp to an ISO date, or transform a path into a hierarchy for hierarchical facets (2.0 only). Currently you can use only one processing instruction at a time.

Example:

.. code-block:: typoscript

    fieldProcessingInstructions {
        changed = timestampToIsoDate
        created = timestampToIsoDate
        endtime = timestampToIsoDate
    }

queue
-----

The Index Queue is a powerful feature introduced with version 2.0. It allows you to easily index any table in your TYPO3 installation by defining a mapping of SolrFieldName = DatabaseTableFieldNameOrContentObject. The table must be configured / described in TCA, though. To index other, external data sources you might want to check out Solr's Data Import Handler (DIH).

The Index Queue comes preconfigured to index pages (enabled by default) and an example configuration for tt_news (provided as a separate TypoScript template).

:Type: Array
:TS Path: plugin.tx_solr.index.queue
:Since: 2.0
:Default: pages

Defines a set of table indexing configurations. By convention the name of the indexing configuration also represents the table name. You can name the indexing configuration differently though by explicitly defining the table as a parameter within the indexing configuration. That's useful when indexing records from one table with different configuration - different single view pages / URLs for example.

Example:

.. code-block:: typoscript

    // enables indexing of tt_news records
    plugin.tx_solr.index.queue.news = 1
    plugin.tx_solr.index.queue.news.fields {
        abstract = short
        author = author
        description = short
        title = title

        // the special SOLR_CONTENT content object cleans HTML and RTE fields
        content = SOLR_CONTENT
        content {
            field = bodytext
        }

        // the special SOLR_RELATION content object resolves relations
        category_stringM = SOLR_RELATION
        category_stringM {
            localField = category
            multiValue = 1
        }

        // the special SOLR_MULTIVALUE content object allows to index multivalue fields
        keywords = SOLR_MULTIVALUE
        keywords {
            field = keywords
        }

        // build the URL through typolink, make sure to use returnLast = url
        url = TEXT
        url {
            typolink.parameter = {$plugin.tt_news.singlePid}
            typolink.additionalParams = &tx_ttnews[tt_news]={field:uid}
            typolink.additionalParams.insertData = 1
            typolink.returnLast = url
            typolink.useCacheHash = 1
        }

        sortAuthor_stringS = author
        sortTitle_stringS  = title
    }

queue.[indexConfig]
-------------------

:Type: Boolean, Array
:TS Path: plugin.tx_solr.index.queue.[indexConfig]
:Since: 2.0
:Default: pages

An indexing configuration defines several parameters about how to index records of a table. By default the name of the indexing configuration is also the name of the table to index.

By setting `plugin.tx_solr.index.queue.[indexConfig] = 1 or 0` you can en- / disable an indexing configuration.

**Note**: you could add `L={field:__solr_index_language}` in the additionalParams of the typolink to link to the correct language version (this was removed from the example above to simplify the example)


queue.[indexConfig].additionalWhereClause
-----------------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].additionalWhereClause
:Since: 2.0

A WHERE clause that is used when initializing the Index Queue, limiting what goes into the Queue. Use this to limit records by page ID or the like.

.. code-block:: typoscript

    // only index standard and mount pages, enabled for search
    plugin.tx_solr.index.queue.pages.additionalWhereClause = doktype IN(1, 7)

queue.[indexConfig].initialPagesAdditionalWhereClause
-----------------------------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].initialPagesAdditionalWhereClause
:Since: 6.1

A WHERE clause that is used when initializing the Index Queue, limiting pages that goes into the Queue.
This filter is applied **prior** to the plugin.tx_solr.index.queue.[indexConfig].additionalWhereClause
filter and hence provides an even stronger filter mechanism - since it can be used to filter away page
ID's that shouldn't be processed at all.

.. code-block:: typoscript

    // Filter away pages that are "spacer" and have no_search, hidden and nav_hide set to zero
    plugin.tx_solr.index.queue.pages.initialPagesAdditionalWhereClause = doktype <> 199 AND no_search = 0 AND hidden = 0 AND nav_hide = 0

queue.[indexConfig].additionalPageIds
-------------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].additionalPageIds
:Since: 2.0

Defines additional pages to take into account when indexing records for example. Especially useful for indexing DAM records or if you have your news outside your site root in a shared folder to use for multiple sites.

Additional page IDs can be provided as comma-separated list.


queue.[indexConfig].table
-------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].table
:Since: 2.0

Sometimes you may want to index records from a table with different configurations, f.e., to generate different single view URLs for tt_news records depending on their category or storage page ID. In these cases you can use a distinct name for the configuration and define the table explicitly.

.. code-block:: typoscript

    plugin.tx_solr.index.queue.generalNews {
      table = tt_news
      fields.url = URL for the general news
      // more field configurations here ...
    }

    // extends the general news configuration
    plugin.tx_solr.index.queue.pressNews < plugin.tx_solr.index.queue.generalNews
    plugin.tx_solr.index.queue.pressNews {
      fields.url = overwriting URL for the press announcements
      // may overwrite or unset more settings from the general configuration
    }

    // completely different configuration
    plugin.tx_solr.index.queue.productNews {
      table = tt_news
      fields.url = URL for the product news
    }



queue.[indexConfig].initialization
----------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].initialization
:Since: 2.0

When initializing the Index Queue through the search backend module the queue tries to determine what records need to be indexed. Usually the default initializer will be enough for this purpose, but this option allows to define a class that will be used to initialize and add records to the Index Queue in special ways.

The extension uses this option for initializing the pages and more specifically to resolve Mount Page trees so they can be indexed too, although only being virtual pages.


queue.[indexConfig].indexer
---------------------------

:Type: String, Array
:TS Path: plugin.tx_solr.index.queue.[indexConfig].indexer
:Since: 2.0

When configuring tables to index a default indexer is used that comes with the extensions. The default indexer resolves the Solr field to database table field mapping as configured. However, in some cases you may reach the limits of TypoScript, when this happens you can configure a specialized indexer using this setting.

The indexer class is loaded using TYPO3's auto loading mechanism, so make sure your class is registered properly. The indexer must extend tx_solr_indexqueue_Indexer.

Example, pages use a specialized indexer:

.. code-block:: typoscript

    plugin.tx_solr.index.queue.pages {
        indexer = tx_solr_indexqueue_PageIndexer
        indexer {
            // add options for the indexer here
        }
    }

Within the indexer configuration you can also define options for the specialized indexer. These are then available within the indexer class in $this->options.

Example, the TypoScript settings are available in PHP:

TypoScript:

.. code-block:: typoscript

    plugin.tx_solr.index.queue.myIndexingConfiguration {
        indexer = tx_myextension_indexqueue_MyIndexer
        indexer {
            someOption = x
            someOtherOption = y
        }
    }


PHP:

.. code-block:: php

    namespace MyVendor\Namespace;

    use ApacheSolrForTypo3\Solr\IndexQueue\Indexer;

    class MyIndexer extends Indexer {
      public function index(tx_solr_indexqueue_Item $item) {
        if ($this->options['someOption']) {
          // ...
        }
      }
    }

queue.[indexConfig].indexingPriority
------------------------------------

:Type: Integer
:TS Path: plugin.tx_solr.index.queue.[indexConfig].indexingPriority
:Since: 2.2
:Default: 0

Allows to define the order in which Index Queue items of different kinds are indexed. Items with higher priority are indexed first.


queue.[indexConfig].fields
--------------------------

:Type: Array
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields
:Since: 2.0

Mapping of Solr field names on the left side to database table field names or content objects on the right side. You must at least provide the title, content, and url fields. TYPO3 system fields like uid, pid, crdate, tstamp and so on are added automatically by the indexer depending on the TCA information of a table.

Example:

.. code-block:: typoscript

    plugin.tx_solr.index.queue.[indexConfig].fields {
      content = bodytext
      title = title
      url = TEXT
      url {
        typolink.parameter = {$plugin.tx_extensionkey.singlePid}
        typolink.additionalParams = &tx_extenionkey[record]={field:uid}
        typolink.additionalParams.insertData = 1
        typolink.returnLast = url
      }
    }

queue.[indexConfig].attachments.fields
--------------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].attachments.fields
:Since: 2.5-dkd

Comma-separated list of fields that hold files. Using this setting allows to tell the file indexer in which fields to look for files to index from records.

Example:

.. code-block:: typoscript

    plugin.tx_solr.index.queue.tt_news.attachments.fields = news_files

queue.[indexConfig].recursiveUpdateFields
-----------------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].recursiveUpdateFields
:Since: 6.1
:Default: Empty

Allows to define a list of additional fields from the pages table that will trigger a recursive update.

.. code-block:: typoscript

    plugin.tx_solr.index.queue.pages.recursiveUpdateFields = title

The example above will trigger a recursive update of pages if the title is changed.

Please note that the following columns should NOT be configured as recursive update fields: "hidden" and "extendToSubpages".
These fields are handled by EXT:solr already internally and thus they will have not effect.

queue.pages.excludeContentByClass
---------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.pages.excludeContentByClass
:Since: 4.0

Can be used for page indexing to exclude a certain css class to be indexed.

Example:

.. code-block:: typoscript

    plugin.tx_solr.index.queue.pages.excludeContentByClass = removeme


The example above will remove the content of items in the page that have the css class "removeme".


queue.pages.allowedPageTypes
----------------------------

:Type: List of Integers
:TS Path: plugin.tx_solr.index.queue.pages.allowedPageTypes
:Since: 3.0
:Default: 1,7

Allows to set the pages types allowed to be indexed.

Even if you have multiple queues for pages, e.g. via different ``additionalWhereClause``'s, you have
to set this value to allow further ``doktype``'s. Restrict the pages to be indexed by each queue via
``additionalWhereClause``.

queue.pages.indexer.authorization.username
------------------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.pages.indexer.authorization.username
:Since: 2.0

Specifies the username to use when indexing pages protected by htaccess.

queue.pages.indexer.authorization.password
------------------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.pages.indexer.authorization.password
:Since: 2.0

Specifies the password to use when indexing pages protected by htaccess.

queue.pages.indexer.frontendDataHelper.scheme
---------------------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.pages.indexer.frontendDataHelper.scheme
:Since: 2.0

Specifies the scheme to use when indexing pages.

queue.pages.indexer.frontendDataHelper.host
-------------------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.pages.indexer.frontendDataHelper.host
:Since: 2.0

Specifies the host to use when indexing pages.

queue.pages.indexer.frontendDataHelper.path
-------------------------------------------

:Type: String
:TS Path: plugin.tx_solr.index.queue.pages.indexer.frontendDataHelper.path
:Since: 2.0

Specifies the path to use when indexing pages.


Indexing Helpers
----------------

To make life even easier the Index Queue provides some indexing helpers. These helpers are content objects that perform cleanup tasks or content transformations.


.. _index-helper-solr-content:

SOLR_CONTENT
~~~~~~~~~~~~

:Since: 2.0

Cleans a database field in a way so that it can be used to fill a Solr document's content field. It removes HTML markup, Javascript and invalid utf-8 chracters.

The helper supports stdWrap on its configuration root.

Example:

.. code-block:: typoscript

    content = SOLR_CONTENT
    content {
        field = bodytext
    }


**Parameters:**

**value**

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].value
:Since: 2.0

Defines the content to clean up. In this case the value would be hard-coded.


.. _index-helper-solr-multivalue:

SOLR_MULTIVALUE
~~~~~~~~~~~~~~~


:Since: 2.0

Turns comma separated strings into an array to be used in a multi value field of an Solr document.

The helper supports stdWrap on its configuration root.

Example:

.. code-block:: typoscript

    keywords = SOLR_MULTIVALUE
    keywords {
        field = tags
        separator = ,
        removeEmptyValues = 1
    }


**Parameters:**

**value**

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].value
:Since: 2.0

Defines the content to clean up. In this case the value would be hard-coded.

**separator**

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].separator
:Since: 2.0
:Default: ,

The separator by which to split the content.

**removeEmptyValues**

:Type: Boolean
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].removeEmptyValues
:Since: 2.0
:Options: 0,1
:Default: 1

The helper will clean the resulting array from empty values by default. If, for some reason, you want to keep empty values just set this to 0.

**removeDuplicateValues**

:Type: Boolean
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].removeDuplicateValues
:Since: 2.9
:Options: 0,1
:Default: 0

Cleans the result from duplicate values.

.. _index-helper-solr-relation:

SOLR_RELATION
~~~~~~~~~~~~~

:Since: 2.0

Resolves relations between tables.

Example:

.. code-block:: typoscript

    category_stringM = SOLR_RELATION
    category_stringM {
        localField = category
        multiValue = 1
    }


**Parameters:**

**localField**

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].localField
:Since: 2.0
:Required: yes

The current record's field name to use to resolve the relation to the foreign table.

**foreignLabelField**

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].foreignLabelField
:Since: 2.0

Usually the label field to retrieve from the related records is determined automatically using TCA, using this option the desired field can be specified explicitly. To specify the label field for recursive relations, the field names can be separated by a dot, e.g. for a category hierarchy to get the name of the parent category one could use "parent.name" (since version:2.9).

**multiValue**

:Type: Boolean
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].multiValue
:Since: 2.0
:Options: 0,1
:Default: 0

Whether to return related records suitable for a multi value field. If this is disabled the related values will be concatenated using the following singleValueGlue.

**singleValueGlue**

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].singleValueGlue
:Since: 2.0
:Default: ", "

When not using multiValue, the related records need to be concatenated using a glue string, by default this is ", " (comma followed by space). Using this option a custom glue can be specified. The custom value must be wrapped by pipe (|) characters to be able to have leading or trailing spaces.

**relationTableSortingField**

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].relationTableSortingField
:Since: 2.2

Field in an mm relation table to sort by, usually "sorting".

**enableRecursiveValueResolution**

:Type: Boolean
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].enableRecursiveValueResolution
:Since: 2.9
:Options: 0,1
:Default: 1

If the specified remote table's label field is a relation to another table, the value will be resolve by following the relation recursively.

**removeEmptyValues**

:Type: Boolean
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].removeEmptyValues
:Since: 2.9
:Options: 0,1
:Default: 1

Removes empty values when resolving relations.

**removeDuplicateValues**

:Type: Boolean
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].removeDuplicateValues
:Since: 2.9
:Options: 0,1
:Default: 0

Removes duplicate values

**additionalWhereClause**

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].additionalWhereClause
:Since: 5.0

Where clause that could be used to limit the related items to a subset that matches this where clause

Example:

.. code-block:: typoscript

    category_stringM = SOLR_RELATION
    category_stringM {
        localField = tags
        multiValue = 1
        additionalWhereClause = pid=2
    }

SOLR_CLASSIFICATION
~~~~~~~~~~~~~~~~~~~

:Since: 8.0

Allows to classify documents based on a configured pattern

Example:

.. code-block:: typoscript

    topic_stringM = SOLR_CLASSIFICATION
    topic_stringM {
        field = __solr_content
        classes {
            programming {
                matchPatterns = php, java, javascript, go
                class = programming
            }
            cms {
                matchPatterns = TYPO3, joomla
                class = cms
            }
            database {
                matchPatterns = mysql, MariaDB, postgreSQL
                class = database
            }
        }
    }


The ```matchPatterns``` can be used to configure pattern that can occure in the content to add that class. In addition ```unmatchPatterns``` can be configured to define patterns that should not occure in the content.

Patterns are regular expressions. You configure everything that is possible with regular expressions.

Example:s

The pattern ```\ssmart[a-z]*\s``` will match everything, that starts with a **space** followed by **smart** ending with any lowercase letter and ending by **space**. This would match e.g. smartphone, smarthome and every other word that starts with ```smart```.

**Note**:

* The configuration ```patterns``` is deprecated with 10.0.0 and will be removed in EXT:solr 11. Please use ```matchPatterns``` and ```unmatchPatterns`` now.


**field**

:Type: String
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].field
:Since: 8.0

Name of the database field, that should be used to as content to classify. The special field __solr_content can
be used during indexing to classify the content of the page or file or any other record that fills the content field before.

**classes**

:Type: Array
:TS Path: plugin.tx_solr.index.queue.[indexConfig].fields.[fieldName].field
:Since: 8.0

Array of classification configurations. Each configuration needs to have the property "patterns", that is a list of patters that need to match and "class", that is the mapped class that will be indexed then.

**Note**:

The output field needs to be a multivalue field since an indexed item can have multiple classes.

enableCommits
-------------

:Type: Boolean
:TS Path: plugin.tx_solr.index.enableCommits
:Since: 6.1
:Default: true

This setting controls whether ext-solr will implicitly cause solr commits as part of its operation.

If this settings is set to false, you need to ensure that something else will periodically call
commits. The solr daemons AutoCommit feature would be a natural choice.

This feature is mainly useful, when you have many installations in the same solr core.

**Note**: Calling some APIs may still cause commits, but these can always be explicitly disabled.
