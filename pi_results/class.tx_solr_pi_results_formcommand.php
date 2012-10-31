<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2012 Ingo Renner <ingo@typo3.org>
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
 * form command class to render the "simple" search form
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package	TYPO3
 * @subpackage	solr
 */
class tx_solr_pi_results_FormCommand implements tx_solr_PluginCommand {

	/**
	 *
	 * @var tslib_cObj
	 */
	protected $cObj;

	/**
	 * Parent plugin
	 *
	 * @var tx_solr_pluginbase_CommandPluginBase
	 */
	protected $parentPlugin;

	/**
	 * Configuration
	 *
	 * @var	array
	 */
	protected $configuration;

	/**
	 * Constructor for class tx_solr_pi_results_FormCommand
	 *
	 * @param tx_solr_pluginbase_CommandPluginBase $parentPlugin parent plugin
	 */
	public function __construct(tx_solr_pluginbase_CommandPluginBase $parentPlugin) {
		$this->cObj = t3lib_div::makeInstance('tslib_cObj');

		$this->parentPlugin  = $parentPlugin;
		$this->configuration = $parentPlugin->conf;
	}

	/**
	 * Provides the values for the markers in the simple form template
	 *
	 * @return array An array containing values for markers in the simple form template
	 * @throws InvalidArgumentException if an registered form modifier fails to implement the required interface tx_solr_FormModifier
	 */
	public function execute() {
		$url = $this->cObj->getTypoLink_URL($this->parentPlugin->conf['search.']['targetPage']);

		$marker = array(
			'action'                    => $url,
			'action_id'                 => intval($this->parentPlugin->conf['search.']['targetPage']),
			'action_language'           => intval($GLOBALS['TSFE']->sys_page->sys_language_uid),
			'action_language_parameter' => 'L',
			'accept-charset'            => $GLOBALS['TSFE']->metaCharset,
			'q'                         => $this->parentPlugin->getCleanUserQuery()
		);

			// hook to modify the search form
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchForm'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr']['modifySearchForm'] as $classReference) {
				$formModifier = t3lib_div::getUserObj($classReference);

				if ($formModifier instanceof tx_solr_FormModifier) {
					if ($formModifier instanceof tx_solr_CommandPluginAware) {
						$formModifier->setParentPlugin($this->parentPlugin);
					}

					$marker = $formModifier->modifyForm($marker, $this->parentPlugin->getTemplate());
				} else {
					throw new InvalidArgumentException(
						'Form modifier "' . $classReference . '" must implement the tx_solr_FormModifier interface.',
						1262864703
					);
				}
			}
		}

		return $marker;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_formcommand.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/pi_results/class.tx_solr_pi_results_formcommand.php']);
}

?>