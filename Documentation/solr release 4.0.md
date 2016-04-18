# Apache Solr for TYPO3 version 4.0 released

We're happy to announce the release of Apache Solr for TYPO3 (EXT:solr)
version 4.0.0. With this release we now support TYPO3 CMS versions 7.6LTS
together with Apache Solr version 4.10.
At the same time we also started making the extension work with TYPO3 8.

## New in this Release

### Support of TYPO3 7.6 LTS and TYPO3 8.0 (Dropping Support of 6.2 LTS)

To stay up-to-date with the TYPO3 core we decided to drop the support of 6.2 LTS
and support 7.6 LTS only. We also made sure that EXT:solr works
with TYPO3 8.0, this will be an ongoing effort until the LTS release of TYPO3 8.
Until then you may experience occasional bugs as we try to keep up with the
core's development.

### Improve the Test Coverage for Fluid Integration and Refactoring

During the preparation for the upcoming fluid integration we used the chance to
refactor the frontend part of the extension.

What we did here:

* Move the logic from the Result/Search and FrequentSearches plugins to service
  classes in order to have the possibility to use them in different places
  (e.g. other controllers)
* Introduce a SearchResultSet and SearchResultSetService to improve the object
   model of search results

### TypoScriptConfiguration Object and Streamlining of Configuration usage

TypoScript configuration is used in many places and accessing it with an object
has some advantages:

1. Merging configuration with Flexforms and other sources is easier
2. We have a single point of access for configuration
3. We can move and remove `isset()`/`is_array()`/`if..else` logic to read and evaluate
   configuration into this object and make the code that is using it more readable
4. Since the implementation is a cross cutting concern for the whole extension,
   it can be used to improve the coverage of integration tests for all parts
5. By reading the annotations later it could be used to automatically generate
    a TypoScript reference

During the preparation of the current release we implemented this
TypoScriptConfiguration object and replaced all parts in the scope of EXT:solr

Migration:

For backwards compatibility the TypoScriptConfiguration object can be used with
array access operators. If you want to read the configuration you can use the
speaking method name for a configuration path or use the methods
`getValueByPath()` or `getObjectByPath()`.

### PHP 7.0 ready

During the development we made sure that the code of EXT:solr works with PHP 7.0.

## General Changes

Beside the major changes that have been mentioned before, there are a few minor
changes that might be interesting:

### ExcludeContentByClass

You can now use a new TypoScript setting `plugin.tx_solr.index.queue.pages.excludeContentByClass`.

e.g.

    plugin.tx_solr.index.queue.pages.excludeContentByClass = removeme

### Removal of q.op Parameter from solrconfig.xml

Since solrconfig.xml contained a default value for the `mm` (MinimumMatch) and
`q.op` parameters and it does not make sense to have both at the same time for
eDismax we removed the `q.op` parameter from the default configuration to avoid
confusion.

### Allow Indexing from Backend Module

To avoid switching from the backend module to scheduler and back, we added a
button to do a simple indexing run with just 10 documents.

### Resolving Overlays of SOLR_RELATION objects

The `SOLR_RELATION` object is now taking an overlay into account when relations
are resolved. This allows you to resolve different relations from a translated
entity for an original entity.

### Auto-Correct in Default Template

The goal of the search is to keep the visitor on the site and provide the best
results for them with the smallest possible interaction.

There was a feature in EXT:solr (searchUsingSpellCheckerSuggestion) that could
be used to automatically trigger a search using the spellchecker's suggestions
when a search with the original term did not retrieve any results. To simplify
the integration we added the needed markup to the default templates so that you
now just need to enable the feature and use it with the shipped default template.

How to use it?

    plugin.tx_solr.search.spellchecking.searchUsingSpellCheckerSuggestion = 1

Use the shipped default templates or add the following snippet to your template:

### Dynamic Field Type for Spellchecking

For spellchecking we had no dynamic field type before. Since 4.0.0 we now ship
the dynamic field types "\*_textSpellS" and "\*_textSpellM" to allow you to
easily define fields for spell checking.

### Usage of Styles and Modals from TYPO3 7 LTS

To keep the styling of the backend up-to-date and use the new JavaScript modals
we updated the backend module to use these new features.

### Add Support of FastVectorHighlighter

Highlighting can be slow for large text documents - f.e. created when
indexing pdf documents - and slow down search queries. To help prevent this
issue we added support for Solr's [FastVectorHighlighter](https://cwiki.apache.org/confluence/display/solr/FastVector+Highlighter)

The FastVectorHighlighter will now be used by default if `fragmentSize` is set
to at least 18 (this is required by the FastVectorHighlighter to work).

### Use Caching Framework in Util::getConfigurationFromPageId

Configuration is retrieved and evaluated many times during indexing. Thus it
made sense to use the TYPO3 caching framework to cache these expensive
operations and improve their performance.

### Use Xliff as Translation Format

Translation files have been migrated to the xliff format. The EXT:solr team
would be happy if you contribute missing translations.

### Resolve affected pages when "extendToSubpages" is set and "hidden" flag is changed

The Record Monitor is now able to evaluate the "hidden" flag on subpages when
the `extendToSubpages` page property is set or unset.

### Add signal after IndexQueueWorkerTask::indexItems

You can now register for a new signal "afterIndexItems".

### Bugfixes

Beside the improvements mentioned before many smaller bugs have been fixed in
this release as well.

## Installation and Updating

This version of EXT:solr is based on the same Apache Solr Version (4.10) as the
last version so an update for the Solr server itself is not needed.

However, a few modifications to the schema and Solr configuration where made.
Please make sure to use the latest solrconfig.xml and schema versions.

You can always check TYPO3's system status report to see whether your setup
meets the requirements and is up-to-date to be used with EXT:solr.

## Deprecations

The following functions and methods have been marked as deprecated and will be
removed in version 5.0

* Util::getTypoScriptObject please use TypoScriptConfiguration::getObjectByPath() instead.
* Util::isValidTypoScriptPath please use TypoScriptConfiguration::isValidPath() instead.
* Util::getTypoScriptValue please use TypoScriptConfiguration::getValueByPath() instead.
* IndexQueue\Queue::getTableToIndexByIndexingConfigurationName please use TypoScriptConfiguration::getIndexQueueTableNameOrFallbackToConfigurationName instead.
* IndexQueue\Queue::getTableIndexingConfigurations please use TypoScriptConfiguration::getEnabledIndexQueueConfigurationNames instead.
* Plugin\PluginBase::search / PluginBase::getSearch / PluginBase::setSearch please use $pi->getSearchResultSetService()->getSearch() instead.
* Plugin\Results\Results::getAdditionalFilters please use $pi->getSearchResultSetService()->getAdditionalFilters() instead.
* Plugin\Results\Results::getNumberOfResultsPerPage use $pi->getSearchResultSetService()->getNumberOfResultsPerPage() instead.
* Plugin\Results\Results::getAdditionalFilters please use $pi->getSearchResultSetService()->getAdditionalFilters() instead.
* TypoScriptConfiguration::offsetGet / offsetExists / offsetSet please use TypoScriptConfiguration::getObjectByPath / isValidPath / getValueByPath instead. These functions have only been implemented for backwards compatibility in will be removed in 5.0

## Outlook

The next release (5.0) will focus on the needed refactorings to allow implementing
fluid template rendering. At the same time we will ship the first version of our
new extension "solrfluid" to our EAP partners.

## Contributors

Like always this release would not have been possible without the help from our
awesome community. These are the contributors for this release.

(patches, comments, bug reports, review, ... in alphabetical order)

* Dmitry Dulepov
* Florian Seirer
* Frank Nägler
* Frans Saris
* Ingo Pfennigstorf
* Ingo Renner
* Marc Bastian Heinrichs
* Markus Friedrich
* Markus Kobligk
* Mickael Vanclooster
* Olivier Dobberkau
* Sascha Egerer
* Timo Schmidt

Thanks to everyone who helped in creating this release!

## How to get involved

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports, and feature requests on [GitHub](https://github.com/TYPO3-Solr/ext-solr)
* Ask or help answering questions in our [Slack channel](https://typo3.slack.com/messages/ext-solr/)
* Provide patches through Pull Request or review and comment on existing [Pull Requests](https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to [www.typo3-solr.com](http://www.typo3-solr.com) or call [dkd](http://www.dkd.de) to sponsor the ongoing development of Apache Solr for TYPO3

