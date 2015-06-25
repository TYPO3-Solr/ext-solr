<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008-2012 Ingo Renner <ingo@typo3.org>
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
 * @package	TYPO3
 * @subpackage	solr
 */
class Tx_Solr_Template {

	const CLEAN_TEMPLATE_YES = TRUE;
	const CLEAN_TEMPLATE_NO  = FALSE;

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

	protected $debugMode         = FALSE;

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

	/**
	 * Copy constructor, sets the clone object's template content to original
	 * object's work subpart.
	 *
	 */
	public function __clone() {
		$this->setTemplateContent($this->getWorkOnSubpart());
	}

	/**
	 * Loads the content of a html template file. Resolves paths beginning with
	 * "EXT:".
	 *
	 * @param	string	path to html template file
	 */
	public function loadHtmlFile($htmlFile) {
		$this->template = $this->cObj->fileResource($htmlFile);

		if (empty($this->template)) {
			throw new RuntimeException(
				'Could not load template file "' . htmlspecialchars($htmlFile) . '"',
				1327490358
			);
		}
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
	 * Finds the view helpers in the template and loads them.
	 *
	 * @return	void
	 */
	protected function initializeViewHelpers($content) {
		$viewHelpersFound = $this->findViewHelpers($content);

		foreach ($viewHelpersFound as $helperKey) {
			if (!isset($this->helpers[strtolower($helperKey)])) {
				$viewHelperLoaded = $this->loadViewHelper($helperKey);

				if (!$viewHelperLoaded) {
						// skipping processing in case we couldn't find a class
						// to handle the view helper
					continue;
				}

				$this->addViewHelper($helperKey);
			}
		}
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
		$success = FALSE;

		if (!isset($this->helpers[strtolower($helperName)])) {
			$viewHelperClassName = $this->loadViewHelper($helperName);

				// could be FALSE if not matching view helper class was found
			if ($viewHelperClassName) {
				try {
					$helperInstance = t3lib_div::makeInstance($viewHelperClassName, $arguments);
					$success = $this->addViewHelperObject($helperName, $helperInstance);
				} catch(Exception $e) {
					if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
						t3lib_div::devLog('exception while adding a viewhelper', 'solr', 3, array(
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
			$viewHelperRealPath = $viewHelperPath;
			if (t3lib_div::isFirstPartOfStr($viewHelperPath, 'Classes/')) {
				$viewHelperRealPath = substr($viewHelperPath, 8);
			}
			if (substr($viewHelperRealPath, -1) == '/') {
				$viewHelperRealPath = substr($viewHelperRealPath, 0, -1);
			}

			$classNamePrefix = t3lib_extMgm::getCN($extensionKey);

			$possibleFilename  = Tx_Solr_Util::underscoredToUpperCamelCase($helperKey) . '.php';
			$possibleClassName = $classNamePrefix . '_' . str_replace('/', '_', $viewHelperRealPath) . '_' . Tx_Solr_Util::underscoredToUpperCamelCase($helperKey);

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
		return FALSE;
	}

	/**
	 * adds an already instantiated viewhelper
	 *
	 * @param $helperName
	 * @param $helperObject
	 * @return	boolean
	 */
	public function addViewHelperObject($helperName, Tx_Solr_ViewHelper $helperObject) {
		$success = FALSE;

		$helperName = strtolower($helperName);

		if (!isset($this->helpers[$helperName])) {
			$this->helpers[$helperName] = $helperObject;
			$success = TRUE;
		}

		return $success;
	}

	/**
	 * Renders the template and fills its markers.
	 *
	 * @return	string the rendered html template with markers replaced with their content
	 */
	public function render($cleanTemplate = FALSE) {

			// process loops
		foreach ($this->loops as $key => $loopVariables) {
			$this->renderLoop($key);
		}

			// process variables
		foreach ($this->variables as $variableKey => $variable) {
			$variableKey     = strtoupper($variableKey);
			$variableMarkers = $this->getVariableMarkers($variableKey, $this->workOnSubpart);

			if (count($variableMarkers)) {
				$resolvedMarkers = $this->resolveVariableMarkers($variableMarkers, $variable);

				$this->workOnSubpart = t3lib_parsehtml::substituteMarkerArray(
					$this->workOnSubpart,
					$resolvedMarkers,
					'###|###'
				);
			}
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
		$this->initializeViewHelpers($this->workOnSubpart);
		$this->workOnSubpart = $this->renderViewHelpers($this->workOnSubpart);

			// process conditions
		$this->workOnSubpart = $this->processConditions($this->workOnSubpart);

			// finally, do a cleanup if not disabled
		if ($cleanTemplate) {
			$this->cleanTemplate();
		}

		return $this->workOnSubpart;
	}

	/**
	 * Escapes marker hashes and the pipe symbol so that they will not be
	 * executed in templates.
	 *
	 * @param string $content Content potentially containing markers
	 * @return string Content with markers escaped
	 */
	public static function escapeMarkers($content) {
		// escape marker hashes
		$content = str_replace('###', '&#35;&#35;&#35;', $content);
		// escape pipe character used for parameter separation
		$content = str_replace('|', '&#124;', $content);

		return $content;
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

		$unresolvedConditions = $this->findConditions($this->workOnSubpart);
		foreach ($unresolvedConditions as $unresolvedCondition) {
				// if condition evaluates to FALSE, remove the content from the template
			$this->workOnSubpart = t3lib_parsehtml::substituteSubpart(
				$this->workOnSubpart,
				$unresolvedCondition['marker'],
				''
			);
		}
	}

	/**
	 * Renders view helpers, detects whether it is a regular marker view helper
	 * or a subpart view helper and passes rendering on to more specialized
	 * render methods for each type.
	 *
	 * @param string $content The content to process by view helpers
	 * @return string the view helper processed content
	 */
	protected function renderViewHelpers($content) {
		$viewHelpersFound = $this->findViewHelpers($content);

		foreach ($viewHelpersFound as $helperKey) {
			if (array_key_exists(strtolower($helperKey), $this->helpers)) {
				$helper = $this->helpers[strtolower($helperKey)];

				if ($helper instanceof Tx_Solr_SubpartViewHelper) {
					$content = $this->renderSubpartViewHelper($helper, $helperKey, $content);
				} else {
					$content = $this->renderMarkerViewHelper($helper, $helperKey, $content);
				}
			}
		}

		return $content;
	}

	/**
	 * Renders single marker view helpers.
	 *
	 * @param	Tx_Solr_ViewHelper	$viewHelper View helper instance to execute.
	 * @param	string	$helperKey The view helper marker key.
	 * @param	string	$content Markup that contains the unsubstituted view helper marker.
	 * @return	string	Markup with the view helper replaced by the content it returned.
	 */
	protected function renderMarkerViewHelper(Tx_Solr_ViewHelper $viewHelper, $helperKey, $content) {
		$viewHelperArgumentLists = $this->getViewHelperArgumentLists($helperKey, $content);

		foreach ($viewHelperArgumentLists as $viewHelperArgumentList) {
			$viewHelperArguments = explode('|', $viewHelperArgumentList);
				// TODO check whether one of the parameters is a Helper
				// itself, if so resolve it before handing it off to the
				// actual helper, this way the order in which viewhelpers
				// get added to the template do not matter anymore
				// may use findViewHelpers()

				// checking whether any of the helper arguments should be
				// replaced by a variable available to the template
			foreach ($viewHelperArguments as $i => $helperArgument) {
				$lowercaseHelperArgument = strtolower($helperArgument);
				if (array_key_exists($lowercaseHelperArgument, $this->variables)) {
					$viewHelperArguments[$i] = $this->variables[$lowercaseHelperArgument];
				}
			}

			$viewHelperContent = $viewHelper->execute($viewHelperArguments);

			$content = t3lib_parsehtml::substituteMarker(
				$content,
				'###' . $helperKey . ':' . $viewHelperArgumentList . '###',
				$viewHelperContent
			);
		}

		return $content;
	}

	/**
	 * Renders subpart view helpers.
	 *
	 * @param	Tx_Solr_ViewHelper	$viewHelper View helper instance to execute.
	 * @param	string	$helperKey The view helper marker key.
	 * @param	string	$content Markup that contains the unsubstituted view helper subpart.
	 * @return	string	Markup with the view helper replaced by the content it returned.
	 */
	protected function renderSubpartViewHelper(Tx_Solr_SubpartViewHelper $viewHelper, $helperKey, $content) {
		$viewHelperArgumentLists = $this->getViewHelperArgumentLists($helperKey, $content);

		foreach ($viewHelperArgumentLists as $viewHelperArgumentList) {
			$subpartMarker = '###' . $helperKey . ':' . $viewHelperArgumentList . '###';

			$subpart = t3lib_parsehtml::getSubpart(
				$content,
				$subpartMarker
			);

			$viewHelperArguments = explode('|', $viewHelperArgumentList);

			$subpartTemplate = clone $this;
			$subpartTemplate->setWorkingTemplateContent($subpart);
			$viewHelper->setTemplate($subpartTemplate);

			try {
				$viewHelperContent = $viewHelper->execute($viewHelperArguments);
			} catch (UnexpectedValueException $e) {
				if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
					t3lib_div::devLog('Exception while rendering a viewhelper', 'solr', 3, array(
						$e->__toString()
					));
				}

				$viewHelperContent = '';
			}

			$content = t3lib_parsehtml::substituteSubpart(
				$content,
				$subpartMarker,
				$viewHelperContent,
				FALSE
			);

				// there might be more occurences of the same subpart maker with
				// the same arguments but different markup to be used...
				// that's the case with the facet subpart vierw helper f.e.
			$furtherOccurences = strpos($content, $subpartMarker);
			if ($furtherOccurences !== FALSE) {
				$content = $this->renderSubpartViewHelper($viewHelper, $helperKey, $content);
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
		$loopContent  = '';
		$loopTemplate = $this->getSubpart('LOOP:' . $loopName);

		$loopContentMarker = 'loop_content:' . $loopName;
		$loopSingleItem    = $this->getSubpart($loopContentMarker, $loopTemplate);
		if (empty($loopSingleItem)) {
				// backwards compatible fallback for unnamed loops
			$loopContentMarker = 'loop_content';
			$loopSingleItem    = $this->getSubpart($loopContentMarker, $loopTemplate);
		}

		$loopMarker    = strtoupper($this->loops[$loopName]['marker']);
		$loopVariables = $this->loops[$loopName]['data'];
		$foundMarkers  = $this->getMarkersFromTemplate($loopSingleItem, $loopMarker . '\.');
		$loopCount     = count($loopVariables);

		if (count($foundMarkers)) {
			$iterationCount = 0;
			foreach ($loopVariables as $value) {
				$resolvedMarkers = $this->resolveVariableMarkers($foundMarkers, $value);
				$resolvedMarkers['LOOP_CURRENT_ITERATION_COUNT'] = ++$iterationCount;

					// pass the whole object / array / variable as is (serialized though)
				$resolvedMarkers[$loopMarker] = serialize($value);

				$currentIterationContent = t3lib_parsehtml::substituteMarkerArray(
					$loopSingleItem,
					$resolvedMarkers,
					'###|###'
				);

				$inLoopMarkers = $this->getMarkersFromTemplate(
					$currentIterationContent,
					'LOOP:',
					FALSE
				);

				$inLoopMarkers = $this->filterProtectedLoops($inLoopMarkers);

				$currentIterationContent = $this->processInLoopMarkers(
					$currentIterationContent,
					$loopName,
					$inLoopMarkers,
					$value
				);

				$currentIterationContent = $this->processConditions($currentIterationContent);

				$loopContent .= $currentIterationContent;
			}
		}

		$loopContent = t3lib_parsehtml::substituteSubpart(
			$loopTemplate,
			'###' . strtoupper($loopContentMarker) . '###',
			$loopContent
		);

		$loopContent = t3lib_parsehtml::substituteMarkerArray(
			$loopContent,
			array('LOOP_ELEMENT_COUNT' => $loopCount),
			'###|###'
		);

		$this->workOnSubpart = t3lib_parsehtml::substituteSubpart(
			$this->workOnSubpart,
			'###LOOP:' . strtoupper($loopName) . '###',
			$loopContent
		);
	}

	/**
	 * Processes marker in a loop that start with LOOP:.
	 *
	 * This is useful especially for calling view helpers with the current
	 * iteration's value as a parameter.
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
				throw new RuntimeException(
					'No matching view helper found for marker "' . $marker . '".',
					1311005284
				);
			}

			$content = str_replace('###LOOP:' . $marker . '###', $markerContent, $content);
		}

		return $content;
	}

	/**
	 * Some marker subparts must be protected and only rendered by their
	 * according commands. This method filters these protected markers from
	 * others when rendering loops so that they are not replaced and left in
	 * the template for rendering by the correct command.
	 *
	 * @param	array	An arry of loop markers found during rendering of a loop.
	 * @return	array	The array with protected subpart markers removed.
	 */
	protected function filterProtectedLoops($loopMarkers) {
		$protectedMarkers = array('result_documents');

		foreach ($loopMarkers as $key => $loopMarker) {
			if (in_array(strtolower($loopMarker), $protectedMarkers)) {
				unset($loopMarkers[$key]);
			}
		}

		return $loopMarkers;
	}

	/**
	 * Processes conditions: finds and evaluates them in HTML code.
	 *
	 * @param	string	HTML
	 */
	protected function processConditions($content) {
			// find conditions
		$conditions = $this->findConditions($content);

			// evaluate conditions
		foreach ($conditions as $condition) {
			if ($this->isVariableMarker($condition['comparand1'])
			|| $this->isVariableMarker($condition['comparand2'])) {
					// unresolved marker => skip, will be resolved later
				continue;
			}

			$conditionResult = $this->evaluateCondition(
				$condition['comparand1'],
				$condition['comparand2'],
				$condition['operator']
			);

			if ($conditionResult) {
					// if condition evaluates to TRUE, simply replace it with
					// the original content to have the surrounding markers removed
				$content = t3lib_parsehtml::substituteSubpart(
					$content,
					$condition['marker'],
					$condition['content']
				);
			} else {
					// if condition evaluates to FALSE, remove the content from the template
				$content = t3lib_parsehtml::substituteSubpart(
					$content,
					$condition['marker'],
					''
				);
			}
		}

		return $content;
	}

	/**
	 * Finds conditions in HTML code.
	 *
	 * Conditions are subparts with markers in the form of
	 *
	 * ###IF:comparand1|operator|comparand2###
	 * Some content only visible if the condition evaluates as TRUE
	 * ###IF:comparand1|operator|comparand2###
	 *
	 * The returned result is an array of arrays describing a found condition.
	 * Each conditions is described as follows:
	 * [marker] the complete marker used to specify the condition
	 * [content] the content wrapped by the condition
	 * [operator] the condition operator
	 * [comparand1] and [comparand2] the comparands.
	 *
	 * @param	string	HTML
	 * @return	array	An array describing the conditions found
	 */
	protected function findConditions($content) {
		$conditions = array();
		$ifMarkers  = $this->getViewHelperArgumentLists('IF', $content, FALSE);

		foreach ($ifMarkers as $ifMarker) {
			list($comparand1, $operator, $comparand2) = explode('|', $ifMarker);

			$ifContent = t3lib_parsehtml::getSubpart(
				$content,
				'###IF:' . $ifMarker . '###'
			);

			$conditions[] = array(
				'marker'     => '###IF:' . $ifMarker . '###',
				'content'    => $ifContent,
				'operator'   => trim($operator),
				'comparand1' => $comparand1,
				'comparand2' => $comparand2
			);
		}

		return $conditions;
	}

	/**
	 * Evaluates conditions.
	 *
	 * Supported operators are ==, !=, <, <=, >, >=, %
	 *
	 * @param string First comaprand
	 * @param string Second comaprand
	 * @param string Operator
	 * @return boolean Boolean evaluation of the condition.
	 * @throws InvalidArgumentException for unknown $operator
	 */
	protected function evaluateCondition($comparand1, $comparand2, $operator) {
		$conditionResult = FALSE;

		switch($operator) {
			case '==':
				$conditionResult = ($comparand1 == $comparand2);
				break;
			case '!=';
				$conditionResult = ($comparand1 != $comparand2);
				break;
			case '<';
				$conditionResult = ($comparand1 < $comparand2);
				break;
			case '<=';
				$conditionResult = ($comparand1 <= $comparand2);
				break;
			case '>';
				$conditionResult = ($comparand1 > $comparand2);
				break;
			case '>=';
				$conditionResult = ($comparand1 >= $comparand2);
				break;
			case '%';
				$conditionResult = ($comparand1 % $comparand2);
				break;
			default:
				throw new InvalidArgumentException(
					'Unknown condition operator "' . htmlspecialchars($operator) . '"',
					1344340207
				);
		}

			// explicit casting, just in case
		$conditionResult = (boolean) $conditionResult;

		return $conditionResult;
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

		$normalizedKeysArray = array();
		foreach($variableValue as $key => $value) {
			$key = $this->normalizeString($key);
			$normalizedKeysArray[$key] = $value;
		}

		foreach ($markers as $marker) {
			$dotPosition = strpos($marker, '.');

			if ($dotPosition !== FALSE) {
				$resolvedValue = NULL;

					// the marker contains a dot, thus we have to resolve the
					// second part of the marker
				$valueSelector = substr($marker, $dotPosition + 1);
				$valueSelector = $this->normalizeString($valueSelector);

				if (is_array($variableValue) && array_key_exists($valueSelector, $normalizedKeysArray)) {
						$resolvedValue = $normalizedKeysArray[$valueSelector];
				} elseif (is_object($variableValue)) {
					$resolveMethod = 'get' . Tx_Solr_Util::camelize($valueSelector);
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

			if (is_array($resolvedValue)) {
					// handling multivalue fields, @see Tx_Solr_ViewHelper_Multivalue
				$resolvedValue = serialize($resolvedValue);
			}

			$resolvedMarkers[$marker] = $resolvedValue;
		}

		return $resolvedMarkers;
	}

	/**
	 * Normalizes the various input formats of the markers to a common format.
	 *
	 * Example:
	 *
	 * FILE_MIME_TYPE_STRING_S => file_mime_type_string_s
	 * file_mime_type_string_s => file_mime_type_string_s
	 * fileMimeType_stringS    => file_mime_type_string_s
	 *
	 * @param	string	A string in upper case with underscores, lowercase with underscores, camel case, or a mix.
	 * @return	string	A lowercased, underscorized version of the given string
	 */
	protected function normalizeString($selector) {
		static $normalizeCache = array();

		if (!isset($normalizeCache[$selector])) {
			$originalSelector = $selector;
			$selector = str_replace('-', '_', $selector);

				// when switching from lowercase to Uppercase in camel cased
				// strings, insert an underscore
			$underscorized = preg_replace('/([a-z])([A-Z])/', '\\1_\\2', $selector);

				// for all other cases - all upper or all lower case
				// we simply lowercase the complete string
			$normalizeCache[$originalSelector] = strtolower($underscorized);
		}

		return $normalizeCache[$selector];
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

		$this->variables[$key] = $value;
	}

	/**
	 * Adds a named loop. The given array is looped over in the template.
	 *
	 * @param	string	loop name
	 * @param	array	variables array
	 */
	public function addLoop($loopName, $markerName, array $variables) {
		$this->loops[$loopName] = array(
			'marker' => $markerName,
			'data'   => $variables
		);
	}

	/**
	 * Gets a list of Markers from the selected subpart.
	 *
	 * @param	string	marker name
	 * @return	array	array of markers
	 */
	public function getMarkersFromTemplate($template, $markerPrefix = '', $capturePrefix = TRUE) {
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
	 */
	public function findMarkers($markerPrefix = '') {
		return $this->getMarkersFromTemplate($this->workOnSubpart, $markerPrefix);
	}

	/**
	 * Gets a list of view helper marker arguments for a given view helper from
	 * the selected subpart.
	 *
	 * @param	string	marker name, can be lower case, doesn't need the ### delimiters
	 * @param	string	subpart markup to search in
	 * @param	boolean	Optionally determines whether duplicate view helpers are removed. Defaults to TRUE.
	 * @return	array	array of markers
	 */
	protected function getViewHelperArgumentLists($helperMarker, $subpart, $removeDuplicates = TRUE) {
			// already tried (and failed) variants:
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
		$markers = $match[1];

		if ($removeDuplicates) {
			$markers = array_unique($markers);
		}

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

			// remove / protect LOOP, LOOP_CONTENT subparts
		$loopIndex = array_search('LOOP', $viewHelpers);
		if ($loopIndex !== FALSE) {
			unset($viewHelpers[$loopIndex]);
		}
		$loopContentIndex = array_search('LOOP_CONTENT', $viewHelpers);
		if ($loopContentIndex !== FALSE) {
			unset($viewHelpers[$loopContentIndex]);
		}

			// remove / protect IF subparts
		$ifIndex = array_search('IF', $viewHelpers);
		if ($ifIndex !== FALSE) {
			unset($viewHelpers[$ifIndex]);
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

	/**
	 * Checks whether a given string is a variable marker
	 *
	 * @param string $potentialVariableMarker String to check whether it is a variable marker
	 * @return boolean TRUE if the string is identified being a variable marker, FALSE otherwise
	 */
	public function isVariableMarker($potentialVariableMarker) {
		$regex = '!###[A-Z0-9_-]*\.[A-Z0-9_-|:.]*\###!is';
		$isVariableMarker = preg_match($regex, $potentialVariableMarker);

		return (boolean) $isVariableMarker;
	}

	public function setTemplateContent($templateContent) {
		return $this->template = $templateContent;
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
	 * @param	boolean	debug mode, TRUE to enable debug mode, FALSE to turn off again, off by default
	 */
	public function setDebugMode($mode) {
		$this->debugMode = (boolean) $mode;
	}
}


if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/Template.php'])	{
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/solr/Classes/Template.php']);
}

?>