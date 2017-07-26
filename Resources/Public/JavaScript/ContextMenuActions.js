/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Module: TYPO3/CMS/Solr/ContextMenuActions
 *
 * JavaScript to handle import/export actions from context menu
 * @exports TYPO3/CMS/Solr/ContextMenuActions
 */
define(function () {
	'use strict';

	/**
	 * @exports TYPO3/CMS/Solr/ContextMenuActions
	 */
	var ContextMenuActions = {};

	ContextMenuActions.initializeSolrConnections = function (table, uid) {
		var url = TYPO3.settings.ajaxUrls['solr_updateConnection'];
		url += '&id=' + uid;
		$.ajax(url).done(function (response) {
			if (response.success) {
				top.TYPO3.Notification.success(
					response.message
				);
			} else {
				top.TYPO3.Notification.error(
					response.message
				);
			}

		});
	};

	return ContextMenuActions;
});
