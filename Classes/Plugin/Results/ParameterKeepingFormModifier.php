<?php
namespace ApacheSolrForTypo3\Solr\Plugin\Results;

/***************************************************************
*  Copyright notice
*
*  (c) 2012-2015 Michel Tremblay <mictre@gmail.com>
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

use ApacheSolrForTypo3\Solr\Plugin\CommandPluginBase;
use ApacheSolrForTypo3\Solr\Template;
use ApacheSolrForTypo3\Solr\Util;
use ApacheSolrForTypo3\Solr\Plugin\CommandPluginAware;
use ApacheSolrForTypo3\Solr\Plugin\FormModifier;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * A form modifier to carry over GET parameters from one request to another if
 * the option plugin.tx_solr.search.keepExistingParametersForNewSearches is
 * enabled.
 *
 * @author Michel Tremblay <mictre@gmail.com>
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class ParameterKeepingFormModifier implements FormModifier, CommandPluginAware {

	/**
	 * Configuration
	 *
	 * @var array
	 */
	protected $configuration;

	/**
	 * The currently active plugin
	 *
	 * @var CommandPluginBase
	 */
	protected $parentPlugin;

	/**
	 * Constructor
	 *
	 */
	public function __construct() {
		$this->configuration = Util::getSolrConfiguration();
	}

	/**
	 * Sets the currently active parent plugin.
	 *
	 * @param CommandPluginBase $parentPlugin Currently active parent plugin
	 */
	public function setParentPlugin(CommandPluginBase $parentPlugin) {
		$this->parentPlugin = $parentPlugin;
	}

	/**
	 * Modifies the search form by providing hidden form fields to transfer
	 * parameters to a news search.
	 *
	 * @param array $markers An array of existing form markers
	 * @param Template $template An instance of the template engine
	 * @return array Array with additional markers for suggestions
	 */
	public function modifyForm(array $markers, Template $template) {
		$hiddenFields = array();

		if ($this->parentPlugin instanceof Results && $this->configuration['search.']['keepExistingParametersForNewSearches']) {
			foreach ($this->parentPlugin->piVars as $key => $value) {
				if ($key == 'page') {
					// must reset page
					continue;
				}

				$name = $this->parentPlugin->prefixId . '[' . $this->cleanFormValue($key) . ']';

				if (is_array($value)) {
					foreach ($value as $k => $v) {
						$hiddenFields[] = '<input type="hidden" name="' . $name . '[' . $this->cleanFormValue($k) . ']" value="' . $this->cleanFormValue($v) . '" />';
					}
				} else {
					$hiddenFields[] = '<input type="hidden" name="' . $name . '" value="' . $this->cleanFormValue($value) . '" />';
				}
			}
		}

		$markers['hidden_parameter_fields'] = implode("\n", $hiddenFields);

		return $markers;
	}

	/**
	 * Cleans a form value that needs to be carried over to the next request
	 * from potential XSS.
	 *
	 * @param string $value Possibly malicious form field value
	 * @return string Cleaned value
	 */
	private function cleanFormValue($value) {
		$value = urldecode($value);

		$value = filter_var(strip_tags($value), FILTER_SANITIZE_STRING);
		$value = GeneralUtility::removeXSS($value);

		return urlencode($value);
	}
}

