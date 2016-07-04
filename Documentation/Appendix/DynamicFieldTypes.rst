.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _appendix-dynamic-fields:

Appendix - Dynamic Fields
=========================


Dynamic fields allow you to add custom fields to Solr documents. That said, you never need to modify Solr's schema (which could cause problems or at least unnecessary additional work when updating the Solr extension).
The following sections describe how to use dynamic fields with your Solr for TYPO3 installation.
Usage of dynamic fields

You can use dynamic fields by following a special naming convention for document fields.
E.g. to create a dynamic field that is a string the field name should end with _stringS. So if you want to
create a field for storing a title you would name it title_stringS. We suggest you use lower camel case for the field name followed by an underscore followed by the dynamic field type "extension".

We've predefined the following dynamic fields:

==============  ==================================  ==========  ===================
Extension       Type                                Multivalue  Comment
==============  ==================================  ==========  ===================
\*_stringS      String                              No
\*_stringM      String                              Yes
\*_boolS        Boolean                             No
\*_boolM        Boolean                             Yes
\*_intS         Integer                             No
\*_intM         Integer                             Yes
\*_sIntS        Sortable        Integer             No          use _tIntS now
\*_sIntM        Sortable        Integer             Yes         use _tIntM now
\*_tIntS        Trie Integer                        No
\*_tIntM        Trie Integer                        Yes
\*_longS        Long                                No
\*_longM        Long                                Yes
\*_sLongS       Sortable Long                       No          use _tLongS now
\*_sLongM       Sortable Long                       Yes         use _tLongM now
\*_tLongS       Trie Long                           No
\*_tLongM       Trie Long                           Yes
\*_floatS       Float                               No
\*_floatM       Float                               Yes
\*_sFloatS      Sortable Float                      No          use _tFloatS now
\*_sFloatM      Sortable Float                      Yes         use _tFloatM now
\*_tFloatS      Trie Float                          No
\*_tFloatM      Trie Float                          Yes
\*_doubleS      Double                              No
\*_doubleM      Double                              Yes
\*_sDoubleS     Sortable Double                     No          use _tDoubleS now
\*_sDoubleM     Sortable Double                     Yes         use _tDoubleM now
\*_tDoubleS     Trie Double 	                    No
\*_tDoubleM     Trie Double 	                    Yes
\*_tDouble4S    Trie Double with Precision Step 4   No
\*_tDouble4M    Trie Double with Precision Step 4   Yes
\*_dateS        Date                                No
\*_dateM        Date                                Yes
\*_tDateS       Trie Date                           No
\*_tDateM       Trie Date                           Yes
\*_random       Random                              No
\*_textS        Text                                No
\*_textM        Text                                Yes
\*_textTS       Text Tight                          No
\*_textTM       Text Tight                          Yes
\*_textSortS    Sortable Text                       No
\*_textSortM    Sortable Text                       Yes
\*_textWstS     Whitespace tokenized Text           No
\*_textWstM     Whitespace tokenized Text           Yes
\*_phoneticS    Phonetic                            No
\*_phoneticM    Phonetic                            Yes
==============  ==================================  ==========  ===================
