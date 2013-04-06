<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Ingo Renner <ingo.renner@dkd.de>
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
 * Manger for the javascript files used throughout the extension's plugins.
 *
 * @author Ingo Renner <ingo.renner@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_JavascriptManager {

	/**
	 * Javascript file configuration.
	 *
	 * @var array
	 */
	protected $configuration;

	/**
	 * Javascript files to load.
	 *
	 * @var array
	 */
	protected static $files = array();

	/**
	 * Raw script snippets to load.
	 *
	 * @var array
	 */
	protected static $snippets = array();

	/**
	 * JavaScript tags to add to the page for the current inxtance
	 *
	 * @var array
	 */
	protected $javaScriptTags = array();


	/**
	 * Constructor.
	 *
	 */
	public function __construct() {
		$this->configuration = tx_solr_Util::getSolrConfiguration();
	}

	/**
	 * Adds a Javascript snippet.
	 *
	 * @param string $identifier Identifier for the snippet.
	 * @param string $snippet The snippet to add.
	 */
	public function addJavascript($identifier, $snippet) {
		if (!array_key_exists($identifier, self::$snippets)) {
			self::$snippets[$identifier] = array(
				'addedToPage' => FALSE,
				'snippet'     => $snippet
			);
		}
	}

	/**
	 * Loads a file by its key as defined in plugin.tx_solr.javascriptFiles.
	 *
	 * @param string $fileKey Key of the file to load.
	 */
	public function loadFile($fileKey) {
		if (!array_key_exists($fileKey, self::$files)) {
			$typoScriptPath = 'plugin.tx_solr.javascriptFiles.' . $fileKey;
			$fileReference  = tx_solr_Util::getTypoScriptValue($typoScriptPath);

			if (!empty($fileReference)) {
				self::$files[$fileKey] = array(
					'addedToPage' => FALSE,
					'file'        => $GLOBALS['TSFE']->tmpl->getFileName($fileReference)
				);
			}
		}
	}

	/**
	 * Adds all the loaded javascript files and snippets to the page.
	 *
	 * Depending on configuration the Javascript is added in header, footer or
	 * not at all if the integrator decides to take care of it himself.
	 *
	 */
	public function addJavascriptToPage() {
		$position = tx_solr_Util::getTypoScriptValue('plugin.tx_solr.javascriptFiles.loadIn');

		if (empty($position)) {
			$position = 'none';
		}

		switch ($position) {
			case 'header':
				$this->buildJavascriptTags();
				$this->addJavascriptToPageHeader();
				break;
			case 'footer':

				$this->registerForEndOfFrontendHook();
				break;
			case 'none':
					// do nothing, JS is handled by the integrator
				break;
			default:
				throw new RuntimeException(
					'Invalid value "' . $position . '" for Javascript position. Choose from "header", "footer", or "none".',
					1336911986
				);
		}
	}

	/**
	 * Adds all the loaded javascript files and snippets to the page header.
	 *
	 */
	protected function addJavascriptToPageHeader() {
		$scripts = implode("\n", $this->javaScriptTags);
		$GLOBALS['TSFE']->additionalHeaderData[uniqid('tx_solr-javascript-')] = $scripts;
	}

	/**
	 * Registers the Javascript Manager to be called when the page is rendered
	 * so that the Javascript can be added at the end of the page.
	 *
	 */
	protected function registerForEndOfFrontendHook() {
		$GLOBALS['TSFE']->TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-cached']['tx_solr-javascript'] = 'tx_solr_JavascriptManager->addJavascriptToPageFooter';
		$GLOBALS['TSFE']->TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['hook_eofe']['tx_solr-javascript'] = 'tx_solr_JavascriptManager->addJavascriptToPageFooter';
	}

	/**
	 * Adds all the loaded javascript files and snippets to the page footer.
	 *
	 * @param array Array of parameters - not used
	 * @param tslib_fe TYPO3 Frontend
	 */
	public function addJavascriptToPageFooter($parameters, tslib_fe $parentObject) {
		$this->buildJavascriptTags();

		$parentObject->content = str_replace(
			'</body>',
			implode("\n", $this->javaScriptTags) . "\n\n</body>",
			$parentObject->content
		);
	}

	/**
	 * Builds the tags to load the javascript needed for different features.
	 *
	 */
	protected function buildJavascriptTags() {
		$filePathPrefix = '';
		if (!empty($GLOBALS['TSFE']->config['config']['absRefPrefix'])) {
			$filePathPrefix = $GLOBALS['TSFE']->config['config']['absRefPrefix'];
		}

			// add files
		foreach (self::$files as $identifier => $file) {
			if (!$file['addedToPage']) {
				self::$files[$identifier]['addedToPage'] = TRUE;
				$this->javaScriptTags[$identifier] = '<script src="' . $filePathPrefix . $file['file'] . '" type="text/javascript"></script>';
			}
		}

			// concatenate snippets
		$snippets = '';
		foreach (self::$snippets as $identifier => $snippet) {
			if (!$snippet['addedToPage']) {
				self::$snippets[$identifier]['addedToPage'] = TRUE;

				$snippets .= "\t/* -- $identifier -- */\n";
				$snippets .= $snippet['snippet'];
				$snippets .= "\n\n";
			}
		}

			// add snippets
		if (!empty($snippets)) {
			$snippets = '<script type="text/javascript">
				/*<![CDATA[*/
			' . $snippets . '
				/*]]>*/
			</script>';

			$this->javaScriptTags['snippets'] = $snippets;
		}
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_javascriptmanager.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_javascriptmanager.php']);
}

?>