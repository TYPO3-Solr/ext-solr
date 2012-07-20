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
	protected $loadedFiles = array();

	/**
	 * Raw script snippets to load.
	 *
	 * @var array
	 */
	protected $scriptSnippets = array();

	/**
	 * Like additionalHeaderData in TSFE, but can be configured to loaded in
	 * head or at the end of the page
	 *
	 * @var array
	 */
	protected static $additionalPageData = array();


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
	 * @param string $script The snippet to add.
	 */
	public function addJavascript($identifier, $script) {
		$this->scriptSnippets[$identifier] = $script;
	}

	/**
	 * Loads a file by its key as defined in plugin.tx_solr.javascriptFiles.
	 *
	 * @param string $fileKey Key of the file to load.
	 */
	public function loadFile($fileKey) {
		$typoScriptPath = 'plugin.tx_solr.javascriptFiles.' . $fileKey;

		$fileReference = tx_solr_Util::getTypoScriptValue($typoScriptPath);
		if (!empty($fileReference)) {
			$this->loadedFiles[$fileKey] = $GLOBALS['TSFE']->tmpl->getFileName($fileReference);
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

		switch ($position) {
			case 'header':
				$this->buildjavascriptTags();
				$this->addJavascriptToPageHeader();
				break;
			case 'footer':
				$this->buildjavascriptTags();
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
		$scripts = implode("\n", self::$additionalPageData);
		$GLOBALS['TSFE']->additionalHeaderData['tx_solr-javascript'] = $scripts;
	}

	/**
	 * Registers the Javascript Manager to be called when the page is rendered
	 * so that the Javascript can be added at the end of the page.
	 *
	 */
	protected function registerForEndOfFrontendHook() {
		$GLOBALS['TSFE']->TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['contentPostProc-cached']['tx_solr-javascript'] = 'EXT:solr/classes/class.tx_solr_javascriptmanager.php:tx_solr_JavascriptManager->addJavascriptToPageFooter';
	}

	/**
	 * Adds all the loaded javascript files and snippets to the page footer.
	 *
	 * @param array Array of parameters - not used
	 * @param tslib_fe TYPO3 Frontend
	 */
	public function addJavascriptToPageFooter($parameters, tslib_fe $parentObject) {
		$scripts = implode("\n", self::$additionalPageData);

		$parentObject->content = str_replace(
			'</body>',
			$scripts . "\n\n</body>",
			$parentObject->content
		);
	}

	/**
	 * Builds the tags to load the javascript needed for different features.
	 *
	 */
	protected function buildjavascriptTags() {
			// add files
		foreach ($this->loadedFiles as $fileKey => $file) {
			if (empty(self::$additionalPageData['tx_solr-javascript-' . $fileKey])) {
				$scriptTag = '<script src="' . $file . '" type="text/javascript"></script>';
				self::$additionalPageData['tx_solr-javascript-' . $fileKey] = $scriptTag;
			}
		}

			// concatenate snippets
		$scriptSnippets = '<script type="text/javascript">
			/*<![CDATA[*/
		';
		foreach ($this->scriptSnippets as $snippetIdentifier => $snippet) {
			$scriptSnippets .= "\t/* -- $snippetIdentifier -- */\n";
			$scriptSnippets .= $snippet;
			$scriptSnippets .= "\n\n";
		}
		$scriptSnippets .= '
			/*]]>*/
		</script>';

		self::$additionalPageData['tx_solr-javascript-snippets'] = $scriptSnippets;
	}

}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_javascriptmanager.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_javascriptmanager.php']);
}

?>