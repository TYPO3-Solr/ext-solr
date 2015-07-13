<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

// TODO maybe rename the class to DetailedStatistics (or so)


/**
 * Enables tracking of detailed statistics
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_Query_Modifier_Statistics implements Tx_Solr_QueryModifier {

	/**
	 * Enables the query's debug mode to get more detailed information.
	 *
	 * @param Tx_Solr_Query The query to modify
	 * @return Tx_Solr_Query The modified query with enabled debugging mode
	 */
	public function modifyQuery(Tx_Solr_Query $query) {
		$query->setDebugMode(TRUE);

		return $query;
	}
}

