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

======================  ===================================  ==========  ===============================================
Extension               Type                                 Multivalue  Comment
======================  ===================================  ==========  ===============================================
\*_stringS              String                               No
\*_stringM              String                               Yes
\*_stringCollatedS      string_collated                      No
\*_stringCollatedM      string_collated                      Yes
\*_binS                 binary                               No          Stored but not indexed
\*_binM                 binary                               Yes         Stored but not indexed
\*_boolS                Boolean                              No
\*_boolM                Boolean                              Yes
\*_intS                 Integer                              No          deprecated use _tIntS now
\*_intM                 Integer                              Yes         deprecated use _tIntM now
\*_sIntS                Sortable        Integer              No          not available use _tIntS now
\*_sIntM                Sortable        Integer              Yes         not available use _tIntM now
\*_tIntS                Trie Integer                         No
\*_tIntM                Trie Integer                         Yes
\*_longS                Long                                 No          deprecated use _tLongS now
\*_longM                Long                                 Yes         deprecated use _tLongM now
\*_sLongS               Sortable Long                        No          not available use _tLongS now
\*_sLongM               Sortable Long                        Yes         not available use _tLongM now
\*_tLongS               Trie Long                            No
\*_tLongM               Trie Long                            Yes
\*_floatS               Float                                No          deprecated use _tFloatS now
\*_floatM               Float                                Yes         deprecated use _tFloatM now
\*_sFloatS              Sortable Float                       No          not available use _tFloatS now
\*_sFloatM              Sortable Float                       Yes         not available use _tFloatM now
\*_tFloatS              Trie Float                           No
\*_tFloatM              Trie Float                           Yes
\*_doubleS              Double                               No          deprecated use _tDoubleS now
\*_doubleM              Double                               Yes         deprecated use _tDoubleM now
\*_sDoubleS             Sortable Double                      No          not available use _tDoubleS now
\*_sDoubleM             Sortable Double                      Yes         not available use _tDoubleM now
\*_tDoubleS             Trie Double                          No
\*_tDoubleM             Trie Double                          Yes
\*_tDouble4S            Trie Double with Precision Step 4    No
\*_tDouble4M            Trie Double with Precision Step 4    Yes
\*_dateS                Date                                 No          deprecated use _tDateS now
\*_dateM                Date                                 Yes         deprecated use _tDateM now
\*_tDateS               Trie Date                            No
\*_tDateM               Trie Date                            Yes
\*_random               Random                               No
\*_textS                Text                                 No
\*_textM                Text                                 Yes
\*_textTS               Text Tight                           No
\*_textTM               Text Tight                           Yes
\*_textSortS            Sortable Text                        No
\*_textSortM            Sortable Text                        Yes
\*_textWstS             Whitespace tokenized Text            No
\*_textWstM             Whitespace tokenized Text            Yes
\*_textEdgeNgramS       Edge Ngram (hello => hello, hell..)  No
\*_textEdgeNgramM       Edge Ngram (hello => hello, hell..)  Yes
\*_textNgramS           Ngram (hello => he,ll,lo,hel,llo)    No
\*_textNgramM           Ngram (hello => he,ll,lo,hel,llo)    Yes
\*_textPath             textPath                             No
\*_textExactS           textExact                            No
\*_textExactM           textExact                            Yes
\*_textSpellS           textSpell                            No
\*_textSpellM           textSpell                            Yes
\*_phoneticS            Phonetic                             No
\*_phoneticM            Phonetic                             Yes
\*_point                point                                No
\*_location             location                             No
\*_coordinate           double
\*_locationRpt          locationRpt                          No
\*_currency             currency                             No
======================  ===================================  ==========  ===============================================