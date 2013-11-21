/***************************************************************
*  Copyright notice
*
*  (c) 2011 Ingo Renner <ingo@typo3.org>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Index Inspector Grid for EXT:solr
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
Ext.ns('TYPO3.tx_solr.IndexInspector');

TYPO3.tx_solr.IndexInspector.DocumentGridPanel = Ext.extend(Ext.grid.GridPanel, {

	initComponent: function() {

		Ext.apply(this, {
			store: new TYPO3.tx_solr.IndexInspector.DocumentStore(),
			colModel: new Ext.grid.ColumnModel([
				TYPO3.tx_solr.IndexInspector.Expander,
				{header: 'Type', dataIndex: 'type', hidden: true},
				{header: 'Title', dataIndex: 'title'}
			]),
			view: new Ext.grid.GroupingView({
				forceFit: true
			}),
			plugins: [TYPO3.tx_solr.IndexInspector.Expander],
			stripeRows: true,
			width: 'auto',
			autoHeight: true,
			enableHdMenu: false,
			enableColumnMove: false,
			enableColumnResize: false,
			border: false
		});

		TYPO3.tx_solr.IndexInspector.DocumentGridPanel.superclass.initComponent.call(this);
	}
});

TYPO3.tx_solr.IndexInspector.DocumentStore = Ext.extend(Ext.data.GroupingStore, {

	constructor: function(cfg) {
		cfg = cfg || {};

		Ext.apply(cfg, {
			storeId: 'indexedDocumentsStore',
			fields: [
				{name: 'type'},
				{name: 'title'}
			],
			groupField: 'type',
			proxy: new Ext.data.DirectProxy({
				directFn: TYPO3.tx_solr.IndexInspector.Remote.indexAction,
				paramOrder: ['pageId']
			}),
			reader:  new Ext.data.JsonReader(),
			autoLoad: true,
			baseParams: {
				pageId: TYPO3.settings.TYPO3.tx_solr.IndexInspector.pageId
			}
		});

		TYPO3.tx_solr.IndexInspector.DocumentStore.superclass.constructor.call(this, cfg);
	}
});

TYPO3.tx_solr.IndexInspector.Expander = new Ext.grid.RowExpander({
	tpl: new Ext.XTemplate(
		'<table border="0">',
		'<tpl for="__data">',
			'<tr><td class="x-selectable" style="cursor: text">{fieldName}</td><td class="x-selectable" style="cursor: text">{fieldValue}</td></tr>',
		'</tpl>',
		'</table>'
	)
});


Ext.onReady(function(){

	var grid = new TYPO3.tx_solr.IndexInspector.DocumentGridPanel();
	grid.render('indexInspectorDocumentList');

});