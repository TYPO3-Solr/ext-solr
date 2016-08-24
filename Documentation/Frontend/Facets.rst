.. This file will be replaced from solrfluid later

======
Facets
======

The goal of a good search is, that the user will find what he is looking for as fast as possible.
To support this goal you can give information from the results to the user to "drill down" or "filter" the results
up to a point where he exactly finds what he was looking for. This concept is called "faceting".

Imagine a user in an online shoe shop is searching for the term "shoe", wouldn't it be useful to allow
the user to filter by "gender", "color" and "brand" to find exactly the model where he is looking for?

In the following paragraphs we will get an overview about the different facet types that can be created on a solr field
just by adding a few lines of configuration.

Facet Types
===========

A solr field can contain different type of data, where different facets make sense. The simplest facet is an option "facet".
The "options facet" just contains a list of values and the user can choose one or many of them. A more complex type
could be a "range facet" on a price field. A facet like this needs to allow to filter on a range of a minimum and a maximum value.

The "type" of a facet can be controlled with the "type" property. When nothing is configured there, the facet will be treated
as option facet.

|

.. code-block:: typoscript

    plugin.tx_solr.search.faceting.facets.[faceName].type = [typeName]


|

Valid types could be: options | queryGroup | hierarchy | dateRange | numericRange

In the following paragraphs we will introduce the available facet types in EXT:solr and show how to configure them.

Option
------

The simplest and most often used facet type is the options facet. It renders the items that could be filtered as a simple list.

To setup an simple options facet you can use the following TypoScript snipped:

|

.. code-block:: typoscript

    plugin.tx_solr.search {
        faceting = 1
        faceting {
            facets {
                contentType {
                    label = Content Type
                    field = type
                }
            }
        }
    }

|

By using this configuration you create an options facet on the solr field "type" with the name "contentType". This field represents the record type, that was
indexed into solr. Shown in the frontend it will look like this:

.. image:: ../Images/Frontend/Facets/options_facet.png


Query Group
-----------

The query group facet renders an option list, comparable to the options facet, but the single options are not created from
plain solr field values. They are created from dynamic queries.

A typical usecase could be, when you want to offer the possiblity to filter on the creation date and want to offer options like "yesterday", "last year" or "more then five years".

With the following example you can configure a query facet:

|

.. code-block:: typoscript

    plugin.tx_solr.search {
        faceting = 1
        faceting {
            facets {
                 age {
                    label = Age
                    field = created
                    type = queryGroup
                    queryGroup {
                        week.query = [NOW/DAY-7DAYS TO *]
                        old.query = [* TO NOW/DAY-7DAYS]
                    }
                }
            }
        }
    }

|

The example above will generate an options facet with the output "week" (for items from the last week) and "old" (for items older then one week).

The output in the frontend will look like this:

.. image:: ../Images/Frontend/Facets/queryGroup_facet.png


Hierarchical
------------

With the hierarchical facets you can render a tree view in the frontend. A common usecase is to render a category tree where a document belongs to.

With the following example you render a very simple rootline tree in TYPO3:

|

.. code-block:: typoscript

    plugin.tx_solr.search {
        faceting = 1
        faceting {
            facets {
                pageHierarchy {
                    field = rootline
                    label = Rootline
                    type = hierarchy
                }
             }
        }
    }

|

The example above just shows a simple example tree that is just rendering the uid's of the rootline as a tree:


.. image:: ../Images/Frontend/Facets/hierarchy_facet.png


**Technical solr background:**

Technically the hierarchical facet for solr is the same as a flat options facet. The support of hierarchies is implemented,
by writing and reading the facet options by a convention:

|

.. code-block:: typoscript

    [depth]-/Level1Label/Level2Label

|

When you follow this convention by writing date into a solr field you can render it as hierarchical facet. As example you can check indexing configuration in EXT:solr (EXT:solr/Configuration/ TypoScript/Solr/setup.txt)

|

.. code-block:: typoscript

    plugin.tx_solr {
        index {
            fieldProcessingInstructions {
                rootline = pageUidToHierarchy
            }
        }
    }

|

In this case the "fieldProcessingInstruction" "pageUidToHierarchy" is used to create the rootline for solr in the conventional way.


Date Range
----------

When you want to provide a range filter on a date field in EXT:solr, you can use the type **"dateRange"**.

The default partial generates a markup with all needed values in data attributes. Together with the provided jQuery ui implementation you can
create an out-of-the-box date range facet.

With the following typoscript you create a date range facet:

|

.. code-block:: typoscript

    plugin.tx_solr.search {
        faceting = 1
        faceting {
            creationDateRange {
                label = Created Between
                field = created
                type = dateRange
            }
        }
    }

|


When you include this template a date range facet will be shown in the frontend that we look like this:

.. image:: ../Images/Frontend/Facets/dateRange_facet.png


Numeric Range
-------------

Beside dates ranges are also useful for numeric values. A typical usecase could be a price slider for a products page.
With the user interface you should be able to filter the documents for a certain price range.

In the default partial, we also ship a partial with data attributes here to support any custom implementation.
By default we will use the current implementation from EXT:solr based on jQueryUi.

The following example configures a **numericRange** facet for the field **"pid"**:

|

.. code-block:: typoscript

    plugin.tx_solr.search {
        faceting = 1
        faceting {
            pidRangeRange {
                field = pid
                label = Pid Range
                type = numericRange
                numericRange {
                    start = 0
                    end = 100
                    gap = 1
                }
            }
        }
    }

|

When you configure a facet on the pid field like this, the frontend will output the following facet:

.. image:: ../Images/Frontend/Facets/numericRange_facet.png


