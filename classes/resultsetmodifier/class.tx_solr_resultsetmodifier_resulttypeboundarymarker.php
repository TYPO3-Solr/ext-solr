<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Markus Goldbach <markus.goldbach@dkd.de>
*  (c) 2011-2012 Ingo Renner <ingo@typo3.org>
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
 * Marks the beginning and end of different types of results. Useful only when
 * results are sorted by type.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @author	Markus Goldbach <markus.goldbach@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_resultsetmodifier_ResultTypeBoundaryMarker implements tx_solr_ResultSetModifier {

	/**
	 * Adds a begin/end flag at the first/last item of a result type
	 *
	 * @see	interfaces/tx_solr_ResultSetModifier::modifyResultSet()
	 */
	public function modifyResultSet(tx_solr_pi_results_ResultsCommand $resultCommand, array $resultSet) {
		$lastResultType = '';

		$numberOfResults = count($resultSet);
		for ($i = 0; $i < $numberOfResults; $i++) {
			if ($resultSet[$i]->type != $lastResultType) {
					// type changed, set begin flag, also matches first result
				$resultSet[$i]->setField(
					'typeBegin',
					$resultSet[$i]->type . '_begin'
				);

					// set end flag on previous result
				if ($i > 0) {
					$resultSet[$i - 1]->setField(
						'typeEnd',
						$resultSet[$i - 1]->type . '_end'
					);
				}

					// remember current type
				$lastResultType = trim($resultSet[$i]->type);
			}
		}

			//set endflag for last item.
		if (!empty($resultSet)) {
			$resultSet[count($resultSet) - 1]->setField(
				'typeEnd',
				$resultSet[count($resultSet) - 1]->type . '_end'
			);
		}

		return $resultSet;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/resultsetmodifier/class.tx_solr_resultsetmodifier_resulttypeboundarymarker.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/resultsetmodifier/class.tx_solr_resultsetmodifier_resulttypeboundarymarker.php']);
}

?>