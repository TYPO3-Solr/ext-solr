# Apache Solr for TYPO3 version 4.0 released

We're happy to announce the release of Apache Solr for TYPO3 (EXT:solr) version 4.0.0. With this release we now support TYPO3 CMS versions 7.6LTS and 8.0 together with Apache Solr version 4.10.

## New in this Release

### Support of TYPO3 7.6 LTS and TYPO3 8.0 (Dropping Support of 6.2 LTS)

To stay uptodate with the TYPO3 core we decided to drop the support of 6.2 LTS and support 7.6 LTS only. At the same time we made sure that EXT:solr is usable with TYPO3 8.0 and that we keep the deprecation log clean.

### Improve the Test Coverage for Fluid Integration and Refactoring

During the preparation of the upcoming fluid integration we used the chance to refactor the frontend part of the extension.

What we did here:

* Move the logic from the Result/Search and FrequentSearches plugin to service classes in order to have the possibility to use them in different places (e.g. other controllers)
* Introduce a SearchResultSet and SearchResultSetService to improve the object model of search results

### TypoScriptConfiguration Object and Streamlining of Configuration usage

TypoScript configuration is used in many places and accessing it with an object has some advantages:

1. Merging configuration with Flexforms and other sources is easier
2. We have a single access point for configuration
3. We can move and remove isset/is_array/if..else logic to read and evaluate configuration
to this object and make the code that is using it more readble
4. Since the implementation is cross cutting the whole extension, it can be used to improve the
coverage of integration tests for all parts
5. By reading the annotations later it could be used to generate a TypoScript reference

During the preparation of the current release we implemented this TypoScriptConfiguration and
replace all parts in the scope of EXT:solr

Migration:

The TypoScriptConfiguration can be used with array access operators, for backwards compatibility. If you want to read the configuration you can use the speaking methode for the pathes or "getValueByPath" or "getObjectByPath".

### PHP 7.0 ready

During the development we made that the code of EXT:solr is usable with PHP 7.0

## General Changes

Beside the major changes that have been mentioned before, there a a few minor changes that might be interesting.

### ExcludeContentByClass

You can now use the TypoScript setting "plugin.tx_solr.index.queue.pages.excludeContentByClass".

e.g.

    plugin.tx_solr.index.queue.pages.excludeContentByClass = removeme

### Remove of q.op Parameter from solrconfig.xml

Since solrconfig.xml contained an default value for the mm (MinimumMatch) and q.op parameter and it does not make sence to have both at the same time for eDismax, we removed this parameter from the default configuration to avoid confusion.

### Allow Indexing from Backend Module

To avoid switching from the backend module to scheduler and back, we added a button to do a simple indexing run with just 10 documents.

### Resolving Overlays of SOLR_RELATION objects

The SOLR_RELATION object is now taking an overlay into account when the relation is resolved. This allows you to resolved different relations from an translated entity then from the original entity.

### Autocorrection in Default Template

The goal of the search is to keep the visitor on the site and provide the best results for him with the smallest possible interaction.

There was a feature in EXT:solr (searchUsingSpellCheckerSuggestion) that could be used to trigger a search from the spellchecking suggestions automatically when a search with the original term did not retrieve any results. To simplify the integration we added the needed parts to the default templates that allows you now to just enable the feature and use it with the shipped default template.

How to use it?

    plugin.tx_solr.search.spellchecking.searchUsingSpellCheckerSuggestion = 1

Use the shipped default templates or add the following snipped to your template:

### Dynamic Field Type for Spellchecking

For spellchecking we had no dynamic field type before. Since 4.0.0 we now ship the schema types "*_textSpellS" and "*_textSpellM" to allow you to define schema fields dynamically

### Usage of Styles and Modals from TYPO3 7 LTS

To keep the styling of the backend uptodate and use the new JavaScript modals we updated the backend module to use these new features.

### Add Support of FastVectorHighlighter

Since highlighting can be slow for large text snippets and slow down the search we added the support for the FastVectorHighlighter (https://cwiki.apache.org/confluence/display/solr/FastVector+Highlighter)

The FastVectorHighlighter requires termPositions="true" and termOffsets="true" to be set in the schema. This is the case for our content field.
So if you want to use the FastVectorHighlighter you just need to enabled:

    plugin.tx_solr.search.results.resultsHighlighting.useFastVectorHighlighter = 1

### Use Caching Framework in Util::getConfigurationFromPageId

The configuration is retrieved and evaluated many times and it makes sence to use the TYPO3 caching framework to cache these expensive operations. No the retrieval is cached and the performance was improved.

### Use Xliff as Translation Format

Tranlationfiles are now migrated to the xliff format. The EXT:solr team would be happy when you contribute missing translations.

### Resolve affected pages when "extendToSubpages" is set and "hidden" flag is changed

The RecordMonitor is now able to evaluate the "hidden" flag on subpages, when "extendToSubpages" is added or removed

### Add signal after IndexQueueWorkerTask::indexItems

When you can to register your own signals after indexing, you are now abled to register for the signal "afterIndexItems".

Beside the tweeks mentioned before a lot of bugs have been fixed in this release.

## Installation and Updating

This version of EXT:solr is based on the same Apache Solr Version (4.10) as the last version. A few modifications to the schema and solr configuration where needed. So please make sure to use the latest solrconfig.xml and schema versions.

## Deprecations

The following functions and methods have been marked as deprecated and will be removed in 5.0

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

The next release (5.0) will ship the needed refactorings to allow the implementation of the fluid rendering. At the same time, we will ship the first version of our extension "solrfluid" to our EAP partners.

## Contributors

Like always this release would not have been possible without the help from our awesome community. These are the contributors for this release.

(patches, comments, bug reports, review, ... in alphabetical order)

* Dmitry Dulepov
* Frank Nägler
* Frans Saris
* Ingo Pfennigstorf
* Ingo Renner
* Olivier Dobberkau
* Marc Bastian Heinrichs
* Markus Friedrich
* Markus Kobligk
* Mickael Vanclooster
* Sascha Egerer
* Timo Schmidt
* Florian Seirer

Thanks to everyone who helped in creating this release!

## How to get involved

There are many ways to get involved with Apache Solr for TYPO3:

* Submit bug reports, and feature requests on GitHub (https://github.com/TYPO3-Solr/ext-solr/issues)
* Ask or help answering questions in our Slack channel: https://typo3.slack.com/messages/ext-solr/
* Provide patches through Pull Request or review and comment on existing Pull Requests (https://github.com/TYPO3-Solr/ext-solr/pulls)
* Go to http://www.typo3-solr.com/ or call dkd to sponsor the ongoing development of Apache Solr for TYPO3

