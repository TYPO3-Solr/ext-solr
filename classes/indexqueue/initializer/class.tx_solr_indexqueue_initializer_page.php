<?php
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
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


/**
 * Index Queue initializer for pages which also covers resolution of mount
 * pages.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_indexqueue_initializer_Page extends tx_solr_indexqueue_initializer_Abstract {

	/**
	 * Initializes Index Queue page items for a certain site.
	 *
	 * @return boolean TRUE if initialization was successful, FALSE on error.
	 * @see tx_solr_indexqueue_initializer_Abstract::initialize()
	 * @see tx_solr_IndexQueueInitializer::initialize()
	 */
	public function initialize() {
			// first do a simple init (quite fast)
		$initialized = parent::initialize();

			// now resolve mount pages, init them separatly (more complex)

		// TODO add a (flag/boolean) column to the IQ item table to mark if an item has additional properties to ease look ups

		return $initialized;
	}


	protected function resolveMountPageTree($mountPageId) {

	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/initializer/class.tx_solr_indexqueue_initializer_page.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/indexqueue/initializer/class.tx_solr_indexqueue_initializer_page.php']);
}

?>
