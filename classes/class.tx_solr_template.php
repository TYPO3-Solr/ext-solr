<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008-2009 Ingo Renner <ingo@typo3.org>
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
 * A template engine to simplify the work with marker based templates. The
 * engine supports easy management of markers, subparts, and even loops.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class tx_solr_Template {

	protected $prefix;
	protected $cObj;
	protected $templateFile;
	protected $template;
	protected $workOnSubpart;
	protected $viewHelperIncludePath;
	protected $helpers           = array();
	protected $loadedHelperFiles = array();
	protected $variables         = array();
	protected $markers           = array();
	protected $subparts          = array();
	protected $loops             = array();

	protected $debugMode         = false;

	/**
	 * Constructor for the html marker template engine.
	 *
	 * @param	tslib_cObj	content object
	 * @param	string	path to the template file
	 * @param	string	name of the subpart to work on
	 */
	public function __construct(tslib_cObj $contentObject, $templateFile, $subpart) {
		$this->cObj = $contentObject;
		$this->templateFile = $templateFile;

		$this->loadHtmlFile($templateFile);
		$this->workOnSubpart($subpart);
	}

	public function __destruct() {

	}

	/**
	 * Loads the content of a html template file. Resolves paths beginning with
	 * "EXT:".
	 *
	 * @param	string	path to html template file
	 */
	public function loadHtmlFile($htmlFile) {
		$this->template = $this->cObj->fileResource($htmlFile);
	}

	/**
	 * Sets the content for the template we're working on
	 *
	 * @param	string	the template's content - usually HTML
	 * @return unknown_type
	 */
	public function setWorkingTemplateContent($templateContent) {
		$this->workOnSubpart = $templateContent;
	}

	/**
	 * Adds an inlcude path where the template engine should look for template
	 * view helpers.
	 *
	 * @param	string	Extension key
	 * @param	string	Path inside the extension to look for view helpers
	 */
	public function addViewHelperIncludePath($extensionKey, $viewHelperPath) {
		$this->viewHelperIncludePath[$extensionKey] = $viewHelperPath;
	}

	/**
	 * adds a view helper
	 *
	 * @param	string	view helper name
	 * @param	array	optional array of arguments
	 */
	public function addViewHelper($helperName, array $arguments = array()) {
		$success = false;

		if (!isset($this->helpers[strtolower($helperName)])) {
			$viewHelperClassName = $this->loadViewHelper($helperName);

				// could be false if not matching view helper class was found
			if ($viewHelperClassName) {
				try {
					$helperInstance = t3lib_div::makeInstance($viewHelperClassName, $arguments);

					$success = $this->addViewHelperObject($helperName, $helperInstance);
				} catch(Exception $e) {
					if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
						t3lib_div::devLog('exception while adding a viewhelper', 'tx_solr', 3, array(
							$e->__toString()
						));
					}
				}
			}
		}

		return $success;
	}

	protected function loadViewHelper($helperKey) {
		if (isset($this->loadedHelperFiles[strtolower($helperKey)])) {
			return $this->loadedHelperFiles[strtolower($helperKey)]['class'];
		}

		foreach ($this->viewHelperIncludePath as $extensionKey => $viewHelperPath) {
			$viewHelperRealPath = $viewHelperRealPath;
			if (t3lib_div::isFirstPartOfStr($viewHelperPath, 'classes/')) {
				$viewHelperRealPath = substr($viewHelperPath, 8);
			}
			if (substr($viewHelperRealPath, -1) == '/') {
				$viewHelperRealPath = substr($viewHelperRealPath, 0, -1);
			}

			$classNamePrefix = t3lib_extMgm::getCN($extensionKey);

			$possibleFilename  = 'class.' . $classNamePrefix . '_' . str_replace('/', '_', $viewHelperRealPath) . '_' . strtolower(str_replace('_', '', $helperKey)) . '.php';
			$possibleClassName = $classNamePrefix . '_' . str_replace('/', '_', $viewHelperRealPath) . '_' . tx_solr_Util::underscoredToUpperCamelCase($helperKey);

			$viewHelperIncludePath = t3lib_extMgm::extPath($extensionKey)
				. $viewHelperPath . $possibleFilename;

			if (file_exists($viewHelperIncludePath)) {
				include_once($viewHelperIncludePath);
				$this->loadedHelperFiles[strtolower($helperKey)] = array(
					'file' => $viewHelperIncludePath,
					'class' => $possibleClassName
				);

				return $possibleClassName;
			}
		}

			// viewhelper could not be found
		return false;
	}

	/**
	 * adds an already instantiated viewhelper
	 *
	 * @param $helperName
	 * @param $helperObject
	 * @return unknown_type
	 */
	public function addViewHelperObject($helperName, tx_solr_ViewHelper $helperObject) {
		$success = false;

		$helperName = strtolower($helperName);

		if (!isset($this->helpers[$helperName])) {
			$this->helpers[$helperName] = $helperObject;
			$success = true;
		}

		return $success;
	}

	/**
	 * Renders the template and fills its markers.
	 *
	 * @return	string the rendered html template with markers replaced with their content
	 */
	public function render($cleanTemplate = true) {

			// process loops
		foreach ($this->loops as $key => $loopVariables) {
			$this->renderLoop($key);
		}

			// process variables
		foreach ($this->variables as $variableKey => $variable) {
			$variableKey     = strtoupper($variableKey);
			$variableMarkers = $this->getVariableMarkers($variableKey, $this->workOnSubpart);

			$resolvedMarkers = $this->resolveVariableMarkers($variableMarkers, $variable);

			$this->workOnSubpart = t3lib_parsehtml::substituteMarkerArray(
				$this->workOnSubpart,
				$resolvedMarkers,
				'###|###'
			);
		}

			// process markers
		$this->workOnSubpart = t3lib_parsehtml::substituteMarkerArray(
			$this->workOnSubpart,
			$this->markers
		);

			// process subparts
		foreach ($this->subparts as $subpart => $content) {
			$this->workOnSubpart = t3lib_parsehtml::substituteSubpart(
				$this->workOnSubpart,
				$subpart,
				$content
			);
		}

			// process view helpers, they need to be the last objects processing the template
		$this->workOnSubpart = $this->processViewHelpers($this->workOnSubpart);

			// finally, do a cleanup if not disable
		if ($cleanTemplate) {
			$this->cleanTemplate();
		}

		return $this->workOnSubpart;
	}

	/**
	 * cleans the template from non-replaced markers and subparts
	 *
	 * @return void
	 */
	public function cleanTemplate() {
		$remainingMarkers = $this->findMarkers();

		foreach ($remainingMarkers as $remainingMarker) {
			$isSubpart = preg_match_all(
				'/(\<\!\-\-[\s]+###' . $remainingMarker . '###.*###'
					. $remainingMarker . '###.+\-\-\>)/sU',
				$this->workOnSubpart,
				$matches,
				PREG_SET_ORDER
			);

			if ($isSubpart) {
				$this->workOnSubpart = str_replace(
					$matches[0][1],
					'',
					$this->workOnSubpart
				);
			} else {
				$this->workOnSubpart = str_replace(
					'###' . $remainingMarker . '###',
					'',
					$this->workOnSubpart
				);
			}
		}
	}

	/**
	 * processes view helper, hands variables over if needed
	 *
	 * @param	string	the content to process by view helpers
	 * @return	string	the view helper processed content
	 * @author	Ingo Renner <ingo@typo3.org>
	 */
	protected function processViewHelpers($content) {
		$viewHelpersFound = $this->findViewHelpers($content);

		foreach ($viewHelpersFound as $helperKey) {
			if (!isset($this->helpers[strtolower($helperKey)])) {
				$this->loadViewHelper($helperKey);
				$this->addViewHelper($helperKey);
			}

			$helper = $this->helpers[strtolower($helperKey)];

			$helperMarkers = $this->getHelperMarkers($helperKey, $content);
			foreach ($helperMarkers as $marker) {
				$helperArguments = explode('|', $marker);
					// TODO check whether one of the parameters is a Helper
					// itself, if so resolve it before handing it of to the
					// actual helper, this way the order in which viewhelpers
					// get added to the template do not matter anymore
					// may use findViewHelpers()

					// checking whether any of the helper arguments should be
					// replaced by a variable available to the template
				foreach ($helperArguments as $i => $helperArgument) {
					$lowercaseHelperArgument = strtolower($helperArgument);
					if (array_key_exists($lowercaseHelperArgument, $this->variables)) {
						$helperArguments[$i] = $this->variables[$lowercaseHelperArgument];
					}
				}

				$viewHelperContent = $helper->execute($helperArguments);

				$content = t3lib_parsehtml::substituteMarker(
					$content,
					'###' . $helperKey . ':' . $marker . '###',
					$viewHelperContent
				);
			}
		}

		return $content;
	}

	/**
	 * Renders the loop for a given loop name.
	 *
	 * @param	string	Key from $this->loops to render
	 */
	protected function renderLoop($loopName) {
		$loopContent    = '';
		$loopTemplate   = $this->getSubpart('LOOP:' . $loopName);
		$loopSingleItem = $this->getSubpart('loop_content', $loopTemplate);
		$loopMarker     = $this->loops[$loopName]['marker'];
		$loopVariables  = $this->loops[$loopName]['data'];
		$foundMarkers   = $this->getMarkersFromTemplate($loopSingleItem, $loopMarker . '\.');

		$iterationCount = 0;
		foreach ($loopVariables as $value) {
			$resolvedMarkers = $this->resolveVariableMarkers($foundMarkers, $value);
			$resolvedMarkers['LOOP_CURRENT_ITERATION_COUNT'] = ++$iterationCount;

			$currentIterationContent = t3lib_parsehtml::substituteMarkerArray(
				$loopSingleItem,
				$resolvedMarkers,
				'###|###'
			);

			$processInLoopMarkers = $this->getMarkersFromTemplate(
				$currentIterationContent,
				'LOOP:',
				false
			);

			$currentIterationContent = $this->processInLoopMarkers(
				$currentIterationContent,
				$loopName,
				$processInLoopMarkers,
				$value
			);

			$loopContent .= $currentIterationContent;
		}

		$this->workOnSubpart = t3lib_parsehtml::substituteSubpart(
			$this->workOnSubpart,
			'###LOOP:' . strtoupper($loopName) . '###',
			$loopContent
		);
	}

	/**
	 * processes marker in a loop that start with LOOP:, this is useful
	 * especially for calling view helper with the current iteration's value
	 * as a parameter
	 *
	 * @param unknown_type $content
	 * @param unknown_type $loopName
	 * @param array $markers
	 * @param unknown_type $currentIterationValue
	 * @return unknown
	 */
	protected function processInLoopMarkers($content, $loopName, array $markers, $currentIterationValue) {

		foreach ($markers as $marker) {
			list($helperName, $helperArguments) = explode(':', $marker);

			$helperName      = strtolower($helperName);
			$helperArguments = explode('|', $helperArguments);

				// checking whether any of the helper arguments should be
				// replaced by the current iteration's value
			if (isset($this->loops[$loopName])) {
				foreach ($helperArguments as $i => $helperArgument) {
					if (strtoupper($this->loops[$loopName]['marker']) == strtoupper($helperArgument)) {
						$helperArguments[$i] = $currentIterationValue;
					}
				}
			}

			if (array_key_exists($helperName, $this->helpers)) {
				$markerContent = $this->helpers[$helperName]->execute($helperArguments);
			} else {
					// TODO turn this into an exception
				$markerContent = 'no matching view helper found for marker "' . $marker . '"';
			}

			$content = str_replace('###LOOP:' . $marker . '###', $markerContent, $content);
		}

		return $content;
	}


	/**
	 * Resolves variables to marker. Markers can be simple markers like
	 * ###MY_MARKER## or "nested" markers which devide their sub values by a
	 * dot: ###MY_MARKER.MY_VALUE### ###MY_MARKER.MY_OTHER_VALUE###.
	 *
	 * @param	array	array with markers to resolve
	 * @param	mixed	the marker's value, which can be an array of values, an object with certain getter methods or a simple string
	 * @return	array	with marker as index and value for it
	 */
	protected function resolveVariableMarkers(array $markers, $variableValue) {
		$resolvedMarkers = array();

		foreach ($markers as $marker) {
			$dotPosition = strpos($marker, '.');

			if ($dotPosition !== false) {
					// the marker contains a dot, thus we have to resolve the
					// second part of the marker
				$valueSelector = strtolower(substr($marker, $dotPosition + 1));

				if (is_array($variableValue)) {
					$resolvedValue = $variableValue[$valueSelector];
				} else if (is_object($variableValue)) {
					$resolveMethod = 'get' . tx_solr_Util::camelize($valueSelector);
					$resolvedValue = $variableValue->$resolveMethod();
				}
			} else {
				$resolvedValue = $variableValue[strtolower($marker)];
			}

			if (is_null($resolvedValue)) {
				if ($this->debugMode) {
					$resolvedValue = '!!! Marker &quot;' . $marker . '&quot; could not be resolved.';
				} else {
					$resolvedValue = '';
				}
			}

			$resolvedMarkers[$marker] = $resolvedValue;
		}

		return $resolvedMarkers;
	}

	/**
	 * Selects a subpart to work on / to apply all operations to.
	 *
	 * @param	string	subpart name
	 */
	public function workOnSubpart($subpartName) {
		$this->workOnSubpart = $this->getSubpart($subpartName, $this->template);
	}

	/**
	 * Retrievs a supart from the given html template.
	 *
	 * @param	string	subpart marker name, can be lowercase, doesn't need the ### delimiters
	 * @return	string	the html subpart
	 */
	public function getSubpart($subpartName, $alternativeTemplate = '') {
		$template = $this->workOnSubpart;

			// set altenative template to work on
		if (!empty($alternativeTemplate)) {
			$template = $alternativeTemplate;
		}

		$subpart = t3lib_parsehtml::getSubpart(
			$template,
			'###' . strtoupper($subpartName) . '###'
		);

		return $subpart;
	}

	/**
	 * Sets a marker's value.
	 *
	 * @param	string	marker name, can be lower case, doesn't need the ### delimiters
	 * @param	string	the marker's value
	 */
	public function addMarker($marker, $content) {
		$this->markers['###' . strtoupper($marker) . '###'] = $content;
	}

	/**
	 * Sets an array of markers with their values.
	 *
	 * @param	array	array of markers
	 */
	public function addMarkerArray(array $markers) {
		foreach ($markers as $marker => $content) {
			$this->addMarker($marker, $content);
		}
	}

	/**
	 * Sets a subpart's value.
	 *
	 * @param	string	subpart name, can be lower case, doesn't need the ### delimiters
	 * @param	string	the subpart's value
	 */
	public function addSubpart($subpartMarker, $content) {
		$this->subparts['###' . strtoupper($subpartMarker) . '###'] = $content;
	}

	/**
	 * Assigns a variable to the html template.
	 * Simple variables can be used like regular markers or in the form
	 * VAR:"VARIABLE_NAME" (without the quotes). Objects can be used in the
	 * form VAR:"OBJECT_NAME"."PROPERTY_NAME" (without the quotes).
	 *
	 * @param	string	variable key
	 * @param	mixed	variable value
	 */
	public function addVariable($key, $value) {
		$key = strtolower($key);

		if (array_key_exists($key, $this->variables)) {
				// TODO throw an exception
		} else {
			$this->variables[$key] = $value;
		}
	}

	/**
	 * Adds a named loop. The given array is looped over in the template.
	 *
	 * @param	string	loop name
	 * @param	array	variables array
	 */
	public function addLoop($loopName, $markerName, array $variables) {
			// TODO make loops objects so that they can be nested
		$this->loops[$loopName] = array(
			'marker' => $markerName,
			'data'   => $variables
		);

		// use foreach with an "Iterator" to run through $variables

	}

	/**
	 * Gets a list of Markers from the selected subpart.
	 *
	 * @param	string	marker name
	 * @return	array	array of markers
	 */
	public function getMarkersFromTemplate($template, $markerPrefix = '', $capturePrefix = true) {
		$regex = '!###([A-Z0-9_-|:.]*)\###!is';

		if (!empty($markerPrefix)) {
			if ($capturePrefix) {
				$regex = '!###(' . strtoupper($markerPrefix) . '[A-Z0-9_-|:.]*)\###!is';
			} else {
				$regex = '!###' . strtoupper($markerPrefix) . '([A-Z0-9_-|:.]*)\###!is';
			}
		}

		preg_match_all($regex, $template, $match);
		$markers = array_unique($match[1]);

		return $markers;
	}

	/**
	 * returns the markers found in the template
	 *
	 * @param	string	a prefix to limit the result to markers beginning with the specified prefix
	 * @return	array	array of markers names
	 * @todo TODO rename to findMarkers and merge with getMarkersFromTemplate()
	 * @todo TODO create a method findSubparts()
	 */
	public function findMarkers($markerPrefix = '') {
		return $this->getMarkersFromTemplate($this->workOnSubpart, $markerPrefix);
	}

	/**
	 * Gets a list of helper markers from the selected subpart.
	 *
	 * @param	string	marker name, can be lower case, doesn't need the ### delimiters
	 * @param	string	subpartname
	 * @return	array	array of markers
	 */
	public function getHelperMarkers($helperMarker, $subpart) {
			// '!###' . $helperMarker . ':([A-Z0-9_-|.]*)\###!is'
			// '!###' . $helperMarker . ':(.*?)\###!is',
			// '!###' . $helperMarker . ':((.*?)+?(\###(.*?)\###(|.*?)?)?)?\###!is'
			// '!###' . $helperMarker . ':((?:###(?:.+?)###)(?:\|.+?)*|(?:.+?)+)###!is'
		preg_match_all(
			'/###' . $helperMarker . ':((?:###.+?###(?:\|.+?)*)|(?:.+?)?)###/si',
			$subpart,
			$match,
			PREG_PATTERN_ORDER
		);
		$markers = array_unique($match[1]);

		return $markers;
	}

	/**
	 * Finds view helpers used in the current subpart being worked on.
	 *
	 * @param	string	A string that should be searched for view helpers.
	 * @return	array	A list of view helper names used in the template.
	 */
	public function findViewHelpers($content) {
		preg_match_all('!###([\w]+):.*?\###!is', $content, $match);
		$viewHelpers = array_unique($match[1]);

			// remove LOOP
		$loopIndex = array_search('LOOP', $viewHelpers);
		if ($loopIndex !== false) {
			unset($viewHelpers[$loopIndex]);
		}

		return $viewHelpers;
	}

	/**
	 * Gets a list of given markers from the selected subpart.
	 *
	 * @param	string	marker name, can be lower case, doesn't need the ### delimiters
	 * @param	string	subpartname
	 * @return	array	array of markers
	 */
	public function getVariableMarkers($variableMarker, $subpart) {
		preg_match_all(
			'!###(' . $variableMarker . '\.[A-Z0-9_-]*)\###!is',
			$subpart,
			$match
		);
		$markers = array_unique($match[1]);

		return $markers;
	}

	public function getTemplateContent() {
		return $this->template;
	}

	public function getWorkOnSubpart() {
		return $this->workOnSubpart;
	}

	/**
	 * Sets the debug mode on or off.
	 *
	 * @param	boolean	debug mode, true to enable debug mode, false to turn off again, off by default
	 */
	public function setDebugMode($mode) {
		$this->debugMode = (boolean) $mode;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_template.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/class.tx_solr_template.php']);
}

?>