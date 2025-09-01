======
Events
======

In version 11 we started with the implementation of events, using the EventDispatcher (PSR-14 Events).

Step by step the events will replace older hooks and signals. In the following you will find a description of the available events

.. tip::
   If you miss any feature useful to the general public, please create a feature request
   `in our issue tracker <https://github.com/TYPO3-Solr/ext-solr/issues/new?template=feature_request.md&title=%5BFEATURE%5D+new+event+for+>`__.



Monitoring
^^^^^^^^^^

Observing and processing of data updates is controlled by the following events:

- ContentElementDeletedEvent, fired if a content element is deleted
- PageMovedEvent, fired if a page is moved
- RecordDeletedEvent, fired if a record is deleted
- RecordGarbageCheckEvent, fired if a record garbage check is triggered
- RecordMovedEvent, fired if a record is moved
- VersionSwappedEvent, fired if a version is swapped

All events implement the DataUpdateEventInterface and at least provide information about the elements uid, table and modified fields.

Data update processing
^^^^^^^^^^^^^^^^^^^^^^

ProcessingFinishedEvent
-----------------------

The ProcessingFinishedEvent indicates that the processing of a data update event is finished, if you want to implement an own monitoring you
can use the ProcessingFinishedEvent to start the processing. The event provides the originally fired data update events listed above, so that
you have all the required information about the update to process.

If you're using the event you're indepent of the monitoring setting, as this event if fired in the immediate and delayed monitoring mode as
soon as an event is processed.

DelayedProcessingQueuingFinishedEvent / DelayedProcessingFinishedEvent
----------------------------------------------------------------------

If you're using the delayed processing (see "monitoringType"), you can use one of the following events:

DelayedProcessingQueuingFinishedEvent
This event is fired as soon as the update event is queued in the event queue (tx_solr_eventqueue_item), you can e.g. use this event to
register own events or to implement processing that has to executed immediately even in delayed monitoring mode.

DelayedProcessingFinishedEvent
The Scheduler task "Event Queue Worker" is required to process the data updates in delayed monitoring mode and will fire the DelayedProcessingFinishedEvent
as soon as an event has been processed. If you require to perform actions only during delayed processing, the event can be used.

SearchUriBuilder
^^^^^^^^^^^^^^^^

The SearchUriBuilder is responsible to build uris, that are used in the searchContext. Since the route enhancer is introduced you can use the following
event to influence the build uris:

- BeforeVariableInCachedUrlAreReplacedEvent
- BeforeCachedVariablesAreProcessedEvent
- AfterUriIsProcessedEvent

Facets
^^^^^^

Currently EXT:solr provides following events for Facets-Component modification:

- AfterFacetIsParsedEvent

Site-Hash
^^^^^^^^^

The SiteHashService provides possibility to override the calculated SiteHash by following PSR-14 events:

- AfterSiteHashHasBeenDeterminedForSiteEvent
- AfterDomainHasBeenDeterminedForSiteEvent deprecated and will be removed on EXT:solr 13.1.x+
