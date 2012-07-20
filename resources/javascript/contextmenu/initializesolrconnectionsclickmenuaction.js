Ext.onReady(function() {
	Ext.apply(TYPO3.Components.PageTree.Actions, {
		initializeSolrConnections: function(node, tree) {
			TYPO3.Solr.ContextMenuActionController.initializeSolrConnectionsByRootPage(
				node.attributes.nodeData,
				function(response) {
					if (response) {
						TYPO3.Flashmessage.display(TYPO3.Severity.error, '', response);
					} else {
						TYPO3.Flashmessage.display(TYPO3.Severity.ok, '', 'Solr Connections initialized');
					}
				},
				this
			);
		}
	});
});
