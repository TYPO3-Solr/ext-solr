<?php
namespace ApacheSolrForTypo3\Solr\ViewHelper;

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

use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * Crop viewhelper to to shorten strings
 * Replaces viewhelpers ###CROP:string|length|cropIndicator|cropFullWords###
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Crop implements ViewHelper {

	// defaults if neither is given trough the view helper marker, nor through TS
	protected $maxLength = 30;
	protected $cropIndicator = '...';
	protected $cropFullWords = TRUE;

	/**
	 * Constructor
	 */
	public function __construct(array $arguments = array()) {
		$configuration = Util::getSolrConfiguration();

		if (!empty($configuration['viewHelpers.']['crop.']['maxLength'])) {
			$this->maxLength = $configuration['viewHelpers.']['crop.']['maxLength'];
		}

		if (!empty($configuration['viewHelpers.']['crop.']['cropIndicator'])) {
			$this->cropIndicator = $configuration['viewHelpers.']['crop.']['cropIndicator'];
		}

		if (isset($configuration['viewHelpers.']['crop.']['cropFullWords'])) {
			$this->cropFullWords = (boolean)$configuration['viewHelpers.']['crop.']['cropFullWords'];
		}
	}

	/**
	 * returns the given string shortened to a max length of optionally set chars.
	 * If no maxLength and/or cropIndicator parameters are set, default values apply
	 *
	 * @param array $arguments
	 * @return string
	 */
	public function execute(array $arguments = array()) {
		$croppedString = $stringToCrop = $arguments[0];

		$maxLength = $this->maxLength;
		if (isset($arguments[1])) {
			$maxLength = (int)$arguments[1];
		}

		$cropIndicator = $this->cropIndicator;
		if (isset($arguments[2])) {
			$cropIndicator = $arguments[2];
		}

		if (!empty($arguments[3])) {
			$this->cropFullWords = TRUE;
		}

		$contentObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
		$contentObject->start(array(), '');
		$croppedString = $contentObject->cropHTML(
			$stringToCrop,
			$maxLength . '|' . $cropIndicator . ($this->cropFullWords ? '|1' : '')
		);

		return $croppedString;
	}
}

