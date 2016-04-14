# Apache Solr for TYPO3 version 3.1.2 released

This release is a bugfix release for the 3.1 branch. Since EXT:solr 4.0.0 will require TYPO3 7.6 LTS or higher
this release provides bugfixes for users that still use TYPO3 6.2 LTS

* Provide needed adjustments to use replaced search content element:

https://github.com/TYPO3-Solr/ext-solr/issues/128

* Index Inspector not working:

https://github.com/TYPO3-Solr/ext-solr/issues/241

* JavaScript files get lost if concatenation and compression are enabled

https://github.com/TYPO3-Solr/ext-solr/issues/264

* Solr doesn't support config.absRefPrefix = auto

https://github.com/TYPO3-Solr/ext-solr/issues/276

* Re-Index after deleting a content element

https://github.com/TYPO3-Solr/ext-solr/issues/231

* Support phrases inside a query string

https://github.com/TYPO3-Solr/ext-solr/issues/307

Thx to the following contributors for patches, reviews and backports:

Ingo Renner
Lorenz Ulrich
Markus Friedrich
Markus Kobligk
Timo Schmidt
