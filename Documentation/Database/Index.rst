Database
========

Database indexes
----------------

Some of the SQL statements performed on the pages table in TYPO3 perform extensive operations while copying
page-trees. These operations can be speeded by by adding 2 indexes to the standard table pages.

The indexes are:
* content_from_pid_deleted (content_from_pid, deleted),
* doktype_no_search_deleted (doktype, no_search, deleted)

It is not required that these indexes are created by in above scenarios considerable performance gains can be achieved.
