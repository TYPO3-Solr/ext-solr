========
Signals
========
Signals are currently only used in the search controller. Every action emits a signal which can be used to change its variables assigned to the view.


Example
^^^^^^^
As an example, the action ``resultsAction`` of the ``Classes/Controller/SearchController`` is used.

.. code-block:: php

		$values = [
			'hasSearched' => $this->searchService->getHasSearched(),
			'additionalFilters' => $this->searchService->getAdditionalFilters(),
			'resultSet' => $searchResultSet,
			'pluginNamespace' => $this->typoScriptConfiguration->getSearchPluginNamespace()
		];

		$values = $this->emitActionSignal(__CLASS__, __FUNCTION__, [$values]);

To fulfill that signal, you can create a slot in your custom extension. All what it needs is an entry in your ``ext_localconf.php`` file:

.. code-block:: php

	/** @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher $signalSlotDispatcher */
	$signalSlotDispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class);
	$signalSlotDispatcher->connect(
	    \ApacheSolrForTypo3\Solr\Controller\SearchController::class,
	    'resultsAction',
	    \YourVendor\yourextkey\Slot\SearchControllerSlot::class, // fully your choice
	    'resultsActionSlot', // fully your choice
	);

An example call look like this:

.. code-block:: php

	/**
	 * @param array $values
	 */
	public function resultsActionSlot($values) {
		$values['foo'] = 'bar'

		return [$values];
	}

Notice: The values array needs to be returned as an single element of an array, because a slot method has to return the same number of arguments like it received.