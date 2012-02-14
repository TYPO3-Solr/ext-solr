<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2012 Ingo Renner <ingo.renner@dkd.de>
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
 * A content object (cObj) to clean a database field in a way so that it can be
 * used to fill a Solr document's content field.
 *
 * @author	Ingo Renner <ingo.renner@dkd.de>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_contentobject_Content {

	const CONTENT_OBJECT_NAME = 'SOLR_CONTENT';

	/**
	 * Executes the SOLR_CONTENT content object.
	 *
	 * Cleans content coming from a database field, removing HTML tags ...
	 *
	 * @param	string	$name content object name 'SOLR_CONTENT'
	 * @param	array	$configuration for the content object
	 * @param	string	$TyposcriptKey not used
	 * @param	tslib_cObj	$contentObject parent cObj
	 * @return	string	serialized array representation of the given list
	 */
	public function cObjGetSingleExt($name, array $configuration, $TyposcriptKey, $contentObject) {
		$contentExtractor = t3lib_div::makeInstance(
			'tx_solr_HtmlContentExtractor',
			$this->getRawContent($contentObject, $configuration)
		);

		return $contentExtractor->getIndexableContent();
	}

	/**
	 * Gets the raw content as configured - a certain value or database field.
	 *
	 * @param	tslib_cObj	$contentObject The original content object
	 * @param	array	$configuration content object configuration
	 * @return	string	The raw content
	 */
	protected function getRawContent($contentObject, $configuration) {
		$content = '';
		if (isset($configuration['value'])) {
			$content = $configuration['value'];
			unset($configuration['value']);
		}

		if(!empty($configuration)) {
			$content = $contentObject->stdWrap($content, $configuration);
		}

		return $content;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/contentobject/class.tx_solr_contentobject_content.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/contentobject/class.tx_solr_contentobject_content.php']);
}

?>