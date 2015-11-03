.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: Includes.txt


.. _start:

=========================================
Apache Solr for TYPO3 - Enterprise Search
=========================================

.. only:: html

	:Classification:
		solr

	:Version:
		|release|

	:Language:
		en

	:Description:
		Apache Solr for TYPO3 - Enterprise search meets enterprise CMS

	:Keywords:
		search, full text, index, solr, lucene, fast, query, results

	:Copyright:
		2009-2015

	:Author:
		Ingo Renner

	:Email:
		ingo@typo3.org

	:License:
		This document is published under the Open Content License
		available from http://www.opencontent.org/opl.shtml

	:Rendered:
		|today|

	The content of this document is related to TYPO3,
	a GNU/GPL CMS/Framework available from `www.typo3.org <http://www.typo3.org/>`_.


	**Table of Contents**

.. toctree::
	:maxdepth: 5
	:titlesonly:
	:glob:

	Setup/Index
	Appendix/*


What does it do?
================

Apache Solr for TYPO3 is the search engine you were looking for with special
features such as Facetted Search or Synonym Support and an incredibly fast
response times of results within milliseconds.

When development started, the primary goal was to create a replacement for
Indexed Search. With the initial public release at T3CON09 in Frankfurt, Germany
that goal was reached and even passed by adding features which Indexed Search
does not support.

The extension is developed in a way so that public versions are released to TER
from time to time. Early access to the development version with more features
can be gained through a paid development partnership. You may check
http://www.typo3-solr.com for more details.

This manual covers all features available in the development version, some of
them may not be available in the versions released to TER. Features which are
only available in the development version are marked.

Feature List
------------

* Facetted Search
* Spellchecking / **Did you mean**
* **Multi Language Support**
* Search word highlighting
* Field Boosting for fine tuning the importance of certain index fields
* **Frontend User Group Access Restrictions Support**
* Stop word Support
* Synonym Support
* **Auto complete / Auto suggest**
* Language Analysis / Support for inflected word forms
* Content Elevation / **Paid Search Results** / Editorial Content
* Sorting of Results
* Content indexing through a near instant backend **Index Queue**
* and more...

Screenshots
-----------

.. image:: Images/solr_screenshot.png
