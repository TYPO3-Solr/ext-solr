# First Preview Version of Solrfluid

Several weeks ago we asked you, what features are important to improve ApacheSolrForTypo3. One major task was the integration of fluid templating into EXT:solr.

The integration of fluid has several advantages:

Reuse your knowledge: Many integrators already know how to use fluid, no need to learn a new templating language
Reuse existing ViewHelpers: There a several existing ViewHelpers for standard tasks, now you can make use of them in your template

Since there where several approaches already started in the community, we started to watch out if we could join the forces to bring the solr extension with fluid to the next level. Together with Frans Saris (beech.it) we started to bring the parts together that he has started back then.

We also had a very productive codesprint in Venlo at the beginning of may. At that time we though about concepts and did a lot of the implementation.

@@@TODO: Add pictures from codesprint@@@

## Refactor the existing code base in EXT:solr and moved some parts into reusable Services

To make it possible to reuse the existing search functionality in EXT:solr in other places, we started to implement an integration test suite that tests many features of the frontend plugin in order to
notice when something breaks.

In the first step we introduced the following classes and moved the existing code into them:

* SearchResultSet
** Responsibility: Holds all information of the results and provides access to them for the view
*** Result documents
*** Number of results
*** Spelling suggestions etc.
* SearchRequest
** Responsibility: Holds all information from the outside that the user has given to perform a search
*** QueryString
*** Current Page
*** Active filters
** Knows which arguments are „persistent“ and need to be kept for a subrequest (e.g. pagination)
* SearchResultSetService
** Responsibility: Triggers the search and creates the SearchResultSet from the response

@@@TODO: Add UML diagram @@@

By having these parts in place we could start in EXT:solrfluid to build the logic on top and use them to trigger searches. Now the same logic is used in both plugins.

## A new Domain Model for Results / Facets etc. with a flexible API

To really split the logic in the View/Controller and Model we decided to implement a new domain model for facets that simplifies the usage for fluid and any other mvc based rendering.

As mentioned before the „ResultSet“ in the new central entry point in the view and allows to retrieve other related domain objects. Therefore we attached also the facets to the SearchResultsSet.
Every facet type is located in one subpackage of „Domain/Search/ResultSet/Facets“. It needs to ship a „parser“ that is responsible to to parse the solr response and create the facet object structure
for the view, based on the response.

For the options facet for example we have the following components:

* Option
** Responsibility: Holds all data of an option (value, label, count...)
* OptionCollection
** Responsibility: Holds all options of a facet and provides and API the query/filter them
* OptionsFacet
** Responsibility: Holds the option facet data (fieldName, label, …) and provides access to the options
* OptionsFacetParser
** Responsibility: Is responsible to build the OptionsFacet/Option object structure from the configuration and response data.


@@@TODO: Add UML diagram @@@



The facets can be extended with own custom facet types and register an own Parser by using the FacetParserRegistry:

FacetParserRegistry::registerParser($className, $type)


## A smart UriBuilder

To centralize and simplify the link creation, EXT:solrfluid ships the SearchUriBuilder. The SearchUriBuilder can be used to easily create subrequest. etc. for facets.
In addition it provides a caching mechanism to reduce the needed typolink calls to a minimum.

## Performance Tuning

Performance is important and it should not get worse when you are using fluid. Therefore we constantly check and tweak the performance during development.

### Compileable ViewHelpers

To speedup the rendering we used compilable ViewHelpers wherever it is possible. With these compiled ViewHelpers TYPO3 can cache the compiled view helpers in the filesystem and
the rendering afterwards is much faster.

### Profiling with XHProf

In our development vagrant box (and also in the TYPO3 homestead box), it is easy to use the profiling tool XHProf from Facebook to measure and optimize the performance.
We used the results from XHProf to optimize the performance constantly during the development.

## Powerful new Features

Beside the feature that fluid provides on it’s own, we’ve implemented a few handy features that help you to realize your requirements with less effort

### Grouping facets by using areas

One requirement for facets is, to have the possibility to render them in various places and not just on one place. An example could be that you want to have
a „type“ facet on top, rendered as a tab and some other facets, like „brand“ or „color“ on the left side.

To allow and simplify this we introduced the „ViewHelpers/Facets/Area“ package. An area is responsible to „filter“ the passed facets on a certain criteria and only provide the matching
facets in the context.

The first implementation of an „area“ is the „grouping“ area. By default every facet is configured to be assigned to the group „main“. And by default this group is also rendered.
You can change the configuration by changing the typoscript configuration „groupName“ to something else, and then render this group in another location.

Beside the „Group“ area, we thinking about other types e.g. „FieldWhitelist“ or „FieldBlacklist“ to allow other „strategies“ of grouping.

### Use the collections api to filter facets and options

Beside the new ViewHelpers our goal was to implement a powerful api to access the data that you need in you view. One example is the Facet and OptionCollection.
You can use the method „getFilteredCopy“ with a closure to retrieve the options that you need with only a few lines of code.

## What is left

The current state is just a working state. We don’t recommend to use it in production!

The following things are planned and need to be done:

* Support the complete feature set for options facets
* Implement FacetParsers for all facet types provided by EXT:solr
* Make sure the extension will work in TYPO3 8
* Ship default partials for sortBy and perPage
* Fix known open bugs
    ** LastSearches and FrequentSearches are not stored
    ** ...

We are happy about your feedback in our slack channel

https://typo3.slack.com/messages/ext-solr/


## Thx

Finally we want to say "Thank you" to Edward Lenssen (beech.it) and Olivier Dobberkau (dkd.de) for supporting the work on the fluid integration.
And last but not least we thank all our partner for supporting the further development of EXT:solr.


## How to get the current state?

If you want to see the current state in action, we provide the current working dev combination of "EXT:solr", "EXT:solrfal" and "EXT:solrfluid"
for our partners in our partner area.


Frans & Timo
