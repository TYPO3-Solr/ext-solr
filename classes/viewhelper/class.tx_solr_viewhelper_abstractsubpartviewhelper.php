<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Stefan Sprenger <stefan.sprenger@dkd.de>
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
 * Abstract subparty viewhelper
 *
 * @author	Stefan Sprenger <stefan.sprenger@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
abstract class tx_solr_viewhelper_AbstractSubpartViewHelper implements tx_solr_SubpartViewHelper {

	/**
	 * @var	tx_solr_Template
	 */
	protected $template = NULL;

	/**
	 * Gets the view helper's subpart template
	 *
	 * @return	tx_solr_Template
	 */
	public function getTemplate() {
		return $this->template;
	}

	/**
	 * Sets the view helper's subpart template
	 *
	 * @param	tx_solr_Template	$template view helper's subpart template
	 */
	public function setTemplate(tx_solr_Template $template) {
		$this->template = $template;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_abstractsubpartviewhelper.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/viewhelper/class.tx_solr_viewhelper_abstractsubpartviewhelper.php']);
}

?>