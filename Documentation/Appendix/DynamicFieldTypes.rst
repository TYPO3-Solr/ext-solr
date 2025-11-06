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
\*_intS                 Integer                              No
\*_intM                 Integer                              Yes
\*_tIntS                Integer                              No          Deprecated use _intS removed in EXT:solr 10
\*_tIntM                Integer                              Yes         Deprecated use _intM removed in EXT:solr 10
\*_longS                Long                                 No
\*_longM                Long                                 Yes
\*_tLongS               Long                                 No          Deprecated use _longS removed in EXT:solr 10
\*_tLongM               Long                                 Yes         Deprecated use _longM removed in EXT:solr 10
\*_floatS               Float                                No
\*_floatM               Float                                Yes
\*_tFloatS              Float                                No          Deprecated use _floatS removed in EXT:solr 10
\*_tFloatM              Float                                Yes         Deprecated use _floatM removed in EXT:solr 10
\*_doubleS              Double                               No
\*_doubleM              Double                               Yes
\*_tDoubleS             Double                               No          Deprecated use _doubleS removed in EXT:solr 10
\*_tDoubleM             Double                               Yes         Deprecated use _doubleS removed in EXT:solr 10
\*_tDouble4S            Double                               No          Deprecated use _double4S removed in EXT:solr 10
\*_tDouble4M            Double                               Yes         Deprecated use _double4M removed in EXT:solr 10
\*_dateS                Date                                 No
\*_dateM                Date                                 Yes
\*_tDateS               Date                                 No          Deprecated use _dateS removed in EXT:solr 10
\*_tDateM               Date                                 Yes         Deprecated use _dateM removed in EXT:solr 10
\*_dateRangeS           DateRange                            No
\*_dateRangeM           DateRange                            Yes
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
\*_textSpellExactS      textSpellExact                       No
\*_textSpellExactM      textSpellExact                       Yes
\*_phoneticS            Phonetic                             No
\*_phoneticM            Phonetic                             Yes
\*_point                point                                No
\*_location             location                             No
\*_coordinate           double
\*_locationRpt          locationRpt                          No
\*_currency             currency                             No
======================  ===================================  ==========  ===============================================
