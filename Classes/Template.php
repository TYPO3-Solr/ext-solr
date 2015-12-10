<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2008-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\ViewHelper\SubpartViewHelper;
use ApacheSolrForTypo3\Solr\ViewHelper\ViewHelper;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * A template engine to simplify the work with marker based templates. The
 * engine supports easy management of markers, subparts, and even loops.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Template
{

    const CLEAN_TEMPLATE_YES = true;
    const CLEAN_TEMPLATE_NO = false;

    protected $prefix;
    protected $cObj;
    protected $templateFile;
    protected $template;
    protected $workOnSubpart;
    protected $viewHelperIncludePath;
    protected $helpers = array();
    protected $loadedHelperFiles = array();
    protected $variables = array();
    protected $markers = array();
    protected $subparts = array();
    protected $loops = array();

    protected $debugMode = false;

    /**
     * Constructor for the html marker template engine.
     *
     * @param ContentObjectRenderer $contentObject content object
     * @param string $templateFile path to the template file
     * @param string $subpart name of the subpart to work on
     */
    public function __construct(
        ContentObjectRenderer $contentObject,
        $templateFile,
        $subpart
    ) {
        $this->cObj = $contentObject;
        $this->templateFile = $templateFile;

        $this->loadHtmlFile($templateFile);
        $this->workOnSubpart($subpart);
    }

    /**
     * @return \TYPO3\CMS\Core\Service\MarkerBasedTemplateService
     */
    protected function getTemplateService()
    {
        return GeneralUtility::makeInstance('TYPO3\CMS\Core\Service\MarkerBasedTemplateService');
    }

    /**
     * Copy constructor, sets the clone object's template content to original
     * object's work subpart.
     *
     */
    public function __clone()
    {
        $this->setTemplateContent($this->getWorkOnSubpart());
    }

    /**
     * Loads the content of a html template file. Resolves paths beginning with
     * "EXT:".
     *
     * @param string $htmlFile path to html template file
     */
    public function loadHtmlFile($htmlFile)
    {
        $this->template = $this->cObj->fileResource($htmlFile);

        if (empty($this->template)) {
            throw new \RuntimeException(
                'Could not load template file "' . htmlspecialchars($htmlFile) . '"',
                1327490358
            );
        }
    }

    /**
     * Sets the content for the template we're working on
     *
     * @param string $templateContent the template's content - usually HTML
     * @return void
     */
    public function setWorkingTemplateContent($templateContent)
    {
        $this->workOnSubpart = $templateContent;
    }

    /**
     * Finds the view helpers in the template and loads them.
     *
     * @return void
     */
    protected function initializeViewHelpers($content)
    {
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
     * Adds an include path where the template engine should look for template
     * view helpers.
     *
     * @param string $extensionKey Extension key
     * @param string $viewHelperPath Path inside the extension to look for view helpers
     */
    public function addViewHelperIncludePath($extensionKey, $viewHelperPath)
    {
        $this->viewHelperIncludePath[$extensionKey] = $viewHelperPath;
    }

    /**
     * Adds a view helper
     *
     * @param string $helperName view helper name
     * @param array $arguments optional array of arguments
     * @return bool
     */
    public function addViewHelper($helperName, array $arguments = array())
    {
        $success = false;

        if (!isset($this->helpers[strtolower($helperName)])) {
            $viewHelperClassName = $this->loadViewHelper($helperName);

            // could be FALSE if not matching view helper class was found
            if ($viewHelperClassName) {
                try {
                    $helperInstance = GeneralUtility::makeInstance($viewHelperClassName,
                        $arguments);
                    $success = $this->addViewHelperObject($helperName,
                        $helperInstance);
                } catch (\Exception $e) {
                    if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
                        GeneralUtility::devLog('exception while adding a viewhelper',
                            'solr', 3, array(
                                $e->__toString()
                            ));
                    }
                }
            }
        }

        return $success;
    }

    protected function loadViewHelper($helperKey)
    {
        if (isset($this->loadedHelperFiles[strtolower($helperKey)])) {
            return $this->loadedHelperFiles[strtolower($helperKey)]['class'];
        }

        foreach ($this->viewHelperIncludePath as $extensionKey => $viewHelperPath) {
            $viewHelperRealPath = $viewHelperPath;
            if (GeneralUtility::isFirstPartOfStr($viewHelperPath, 'Classes/')) {
                $viewHelperRealPath = substr($viewHelperPath, 8);
            }
            if (substr($viewHelperRealPath, -1) == '/') {
                $viewHelperRealPath = substr($viewHelperRealPath, 0, -1);
            }

            $classNamePrefix = ExtensionManagementUtility::getCN($extensionKey);

            //FIXME for PHP 5.4.32, 5.5.16: $classNamePrefix = ucwords($classNamePrefix, '_');
            $classNamePrefix = explode('_', $classNamePrefix);
            $classNamePrefix = array_map('ucfirst', $classNamePrefix);
            $classNamePrefix = implode('_', $classNamePrefix);

            $possibleFilename = Util::underscoredToUpperCamelCase($helperKey) . '.php';
            $possibleClassName = $classNamePrefix . '_' . str_replace('/', '_',
                    $viewHelperRealPath) . '_' . Util::underscoredToUpperCamelCase($helperKey);

            $viewHelperIncludePath = ExtensionManagementUtility::extPath($extensionKey)
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

        // view helper could not be found
        return false;
    }

    /**
     * adds an already instantiated view helper
     *
     * @param $helperName
     * @param ViewHelper $helperObject
     * @return boolean
     */
    public function addViewHelperObject($helperName, ViewHelper $helperObject)
    {
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
     * @return string the rendered html template with markers replaced with their content
     */
    public function render($cleanTemplate = false)
    {

        // process loops
        foreach ($this->loops as $key => $loopVariables) {
            $this->renderLoop($key);
        }

        // process variables
        foreach ($this->variables as $variableKey => $variable) {
            $variableKey = strtoupper($variableKey);
            $variableMarkers = $this->getVariableMarkers($variableKey,
                $this->workOnSubpart);

            if (count($variableMarkers)) {
                $resolvedMarkers = $this->resolveVariableMarkers($variableMarkers,
                    $variable);

                $this->workOnSubpart = $this->getTemplateService()->substituteMarkerArray(
                    $this->workOnSubpart,
                    $resolvedMarkers,
                    '###|###'
                );
            }
        }

        // process markers
        $this->workOnSubpart = $this->getTemplateService()->substituteMarkerArray(
            $this->workOnSubpart,
            $this->markers
        );

        // process subparts
        foreach ($this->subparts as $subpart => $content) {
            $this->workOnSubpart = $this->getTemplateService()->substituteSubpart(
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
    public static function escapeMarkers($content)
    {
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
    public function cleanTemplate()
    {
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
            $this->workOnSubpart = $this->getTemplateService()->substituteSubpart(
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
    protected function renderViewHelpers($content)
    {
        $viewHelpersFound = $this->findViewHelpers($content);

        foreach ($viewHelpersFound as $helperKey) {
            if (array_key_exists(strtolower($helperKey), $this->helpers)) {
                $helper = $this->helpers[strtolower($helperKey)];

                if ($helper instanceof SubpartViewHelper) {
                    $content = $this->renderSubpartViewHelper($helper,
                        $helperKey, $content);
                } else {
                    $content = $this->renderMarkerViewHelper($helper,
                        $helperKey, $content);
                }
            }
        }

        return $content;
    }

    /**
     * Renders single marker view helpers.
     *
     * @param ViewHelper $viewHelper View helper instance to execute.
     * @param string $helperKey The view helper marker key.
     * @param string $content Markup that contains the unsubstituted view helper marker.
     * @return string Markup with the view helper replaced by the content it returned.
     */
    protected function renderMarkerViewHelper(
        ViewHelper $viewHelper,
        $helperKey,
        $content
    ) {
        $viewHelperArgumentLists = $this->getViewHelperArgumentLists($helperKey,
            $content);

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
                if (array_key_exists($lowercaseHelperArgument,
                    $this->variables)) {
                    $viewHelperArguments[$i] = $this->variables[$lowercaseHelperArgument];
                }
            }

            $viewHelperContent = $viewHelper->execute($viewHelperArguments);

            $content = $this->getTemplateService()->substituteMarker(
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
     * @param SubpartViewHelper $viewHelper View helper instance to execute.
     * @param string $helperKey The view helper marker key.
     * @param string $content Markup that contains the unsubstituted view helper subpart.
     * @return string Markup with the view helper replaced by the content it returned.
     */
    protected function renderSubpartViewHelper(
        SubpartViewHelper $viewHelper,
        $helperKey,
        $content
    ) {
        $viewHelperArgumentLists = $this->getViewHelperArgumentLists($helperKey,
            $content);

        foreach ($viewHelperArgumentLists as $viewHelperArgumentList) {
            $subpartMarker = '###' . $helperKey . ':' . $viewHelperArgumentList . '###';

            $subpart = $this->getTemplateService()->getSubpart(
                $content,
                $subpartMarker
            );

            $viewHelperArguments = explode('|', $viewHelperArgumentList);

            $subpartTemplate = clone $this;
            $subpartTemplate->setWorkingTemplateContent($subpart);
            $viewHelper->setTemplate($subpartTemplate);

            try {
                $viewHelperContent = $viewHelper->execute($viewHelperArguments);
            } catch (\UnexpectedValueException $e) {
                if ($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.']['logging.']['exceptions']) {
                    GeneralUtility::devLog('Exception while rendering a viewhelper',
                        'solr', 3, array(
                            $e->__toString()
                        ));
                }

                $viewHelperContent = '';
            }

            $content = $this->getTemplateService()->substituteSubpart(
                $content,
                $subpartMarker,
                $viewHelperContent,
                false
            );

            // there might be more occurrences of the same subpart maker with
            // the same arguments but different markup to be used...
            // that's the case with the facet subpart view helper f.e.
            $furtherOccurrences = strpos($content, $subpartMarker);
            if ($furtherOccurrences !== false) {
                $content = $this->renderSubpartViewHelper($viewHelper,
                    $helperKey, $content);
            }
        }

        return $content;
    }

    /**
     * Renders the loop for a given loop name.
     *
     * @param string $loopName Key from $this->loops to render
     */
    protected function renderLoop($loopName)
    {
        $loopContent = '';
        $loopTemplate = $this->getSubpart('LOOP:' . $loopName);

        $loopContentMarker = 'loop_content:' . $loopName;
        $loopSingleItem = $this->getSubpart($loopContentMarker, $loopTemplate);
        if (empty($loopSingleItem)) {
            // backwards compatible fallback for unnamed loops
            $loopContentMarker = 'loop_content';
            $loopSingleItem = $this->getSubpart($loopContentMarker,
                $loopTemplate);
        }

        $loopMarker = strtoupper($this->loops[$loopName]['marker']);
        $loopVariables = $this->loops[$loopName]['data'];
        $foundMarkers = $this->getMarkersFromTemplate($loopSingleItem,
            $loopMarker . '\.');
        $loopCount = count($loopVariables);

        if (count($foundMarkers)) {
            $iterationCount = 0;
            foreach ($loopVariables as $value) {
                $resolvedMarkers = $this->resolveVariableMarkers($foundMarkers,
                    $value);
                $resolvedMarkers['LOOP_CURRENT_ITERATION_COUNT'] = ++$iterationCount;

                // pass the whole object / array / variable as is (serialized though)
                $resolvedMarkers[$loopMarker] = serialize($value);

                $currentIterationContent = $this->getTemplateService()->substituteMarkerArray(
                    $loopSingleItem,
                    $resolvedMarkers,
                    '###|###'
                );

                $inLoopMarkers = $this->getMarkersFromTemplate(
                    $currentIterationContent,
                    'LOOP:',
                    false
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

        $loopContent = $this->getTemplateService()->substituteSubpart(
            $loopTemplate,
            '###' . strtoupper($loopContentMarker) . '###',
            $loopContent
        );

        $loopContent = $this->getTemplateService()->substituteMarkerArray(
            $loopContent,
            array('LOOP_ELEMENT_COUNT' => $loopCount),
            '###|###'
        );

        $this->workOnSubpart = $this->getTemplateService()->substituteSubpart(
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
     * @param string $content
     * @param string $loopName
     * @param array $markers
     * @param string $currentIterationValue
     * @return string
     */
    protected function processInLoopMarkers(
        $content,
        $loopName,
        array $markers,
        $currentIterationValue
    ) {
        foreach ($markers as $marker) {
            list($helperName, $helperArguments) = explode(':', $marker);

            $helperName = strtolower($helperName);
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
                throw new \RuntimeException(
                    'No matching view helper found for marker "' . $marker . '".',
                    1311005284
                );
            }

            $content = str_replace('###LOOP:' . $marker . '###', $markerContent,
                $content);
        }

        return $content;
    }

    /**
     * Some marker subparts must be protected and only rendered by their
     * according commands. This method filters these protected markers from
     * others when rendering loops so that they are not replaced and left in
     * the template for rendering by the correct command.
     *
     * @param array $loopMarkers An array of loop markers found during rendering of a loop.
     * @return array The array with protected subpart markers removed.
     */
    protected function filterProtectedLoops($loopMarkers)
    {
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
     * @param string $content HTML
     * @return string
     */
    protected function processConditions($content)
    {
        // find conditions
        $conditions = $this->findConditions($content);

        // evaluate conditions
        foreach ($conditions as $condition) {
            if ($this->isVariableMarker($condition['comparand1'])
                || $this->isVariableMarker($condition['comparand2'])
            ) {
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
                $content = $this->getTemplateService()->substituteSubpart(
                    $content,
                    $condition['marker'],
                    $condition['content']
                );
            } else {
                // if condition evaluates to FALSE, remove the content from the template
                $content = $this->getTemplateService()->substituteSubpart(
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
     * @param string $content HTML
     * @return array An array describing the conditions found
     */
    protected function findConditions($content)
    {
        $conditions = array();
        $ifMarkers = $this->getViewHelperArgumentLists('IF', $content, false);

        foreach ($ifMarkers as $ifMarker) {
            list($comparand1, $operator, $comparand2) = explode('|', $ifMarker);

            $ifContent = $this->getTemplateService()->getSubpart(
                $content,
                '###IF:' . $ifMarker . '###'
            );

            $conditions[] = array(
                'marker' => '###IF:' . $ifMarker . '###',
                'content' => $ifContent,
                'operator' => trim($operator),
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
     * @param string $comparand1 First comparand
     * @param string $comparand2 Second comparand
     * @param string $operator Operator
     * @return boolean Boolean evaluation of the condition.
     * @throws \InvalidArgumentException for unknown $operator
     */
    protected function evaluateCondition($comparand1, $comparand2, $operator)
    {
        $conditionResult = false;

        switch ($operator) {
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
                throw new \InvalidArgumentException(
                    'Unknown condition operator "' . htmlspecialchars($operator) . '"',
                    1344340207
                );
        }

        // explicit casting, just in case
        $conditionResult = (boolean)$conditionResult;

        return $conditionResult;
    }

    /**
     * Resolves variables to marker. Markers can be simple markers like
     * ###MY_MARKER## or "nested" markers which devide their sub values by a
     * dot: ###MY_MARKER.MY_VALUE### ###MY_MARKER.MY_OTHER_VALUE###.
     *
     * @param array $markers array with markers to resolve
     * @param mixed $variableValue the marker's value, which can be an array of values, an object with certain getter methods or a simple string
     * @return array with marker as index and value for it
     */
    protected function resolveVariableMarkers(array $markers, $variableValue)
    {
        $resolvedMarkers = array();

        $normalizedKeysArray = array();
        foreach ($variableValue as $key => $value) {
            $key = $this->normalizeString($key);
            $normalizedKeysArray[$key] = $value;
        }

        foreach ($markers as $marker) {
            $dotPosition = strpos($marker, '.');

            if ($dotPosition !== false) {
                $resolvedValue = null;

                // the marker contains a dot, thus we have to resolve the
                // second part of the marker
                $valueSelector = substr($marker, $dotPosition + 1);
                $valueSelector = $this->normalizeString($valueSelector);

                if (is_array($variableValue) && array_key_exists($valueSelector,
                        $normalizedKeysArray)
                ) {
                    $resolvedValue = $normalizedKeysArray[$valueSelector];
                } elseif (is_object($variableValue)) {
                    $resolveMethod = 'get' . Util::camelize($valueSelector);
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
                // handling multivalue fields, @see ApacheSolrForTypo3\Solr\ViewHelper\Multivalue
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
     * @param string $selector A string in upper case with underscores, lowercase with underscores, camel case, or a mix.
     * @return string A lowercased, underscorized version of the given string
     */
    protected function normalizeString($selector)
    {
        static $normalizeCache = array();

        if (!isset($normalizeCache[$selector])) {
            $originalSelector = $selector;
            $selector = str_replace('-', '_', $selector);

            // when switching from lowercase to Uppercase in camel cased
            // strings, insert an underscore
            $underscorized = preg_replace('/([a-z])([A-Z])/', '\\1_\\2',
                $selector);

            // for all other cases - all upper or all lower case
            // we simply lowercase the complete string
            $normalizeCache[$originalSelector] = strtolower($underscorized);
        }

        return $normalizeCache[$selector];
    }

    /**
     * Selects a subpart to work on / to apply all operations to.
     *
     * @param string $subpartName subpart name
     */
    public function workOnSubpart($subpartName)
    {
        $this->workOnSubpart = $this->getSubpart($subpartName, $this->template);
    }

    /**
     * Retrieves a subpart from the given html template.
     *
     * @param string $subpartName subpart marker name, can be lowercase, doesn't need the ### delimiters
     * @param string $alternativeTemplate
     * @return string the html subpart
     */
    public function getSubpart($subpartName, $alternativeTemplate = '')
    {
        $template = $this->workOnSubpart;

        // set alternative template to work on
        if (!empty($alternativeTemplate)) {
            $template = $alternativeTemplate;
        }

        $subpart = $this->getTemplateService()->getSubpart(
            $template,
            '###' . strtoupper($subpartName) . '###'
        );

        return $subpart;
    }

    /**
     * Sets a marker's value.
     *
     * @param string $marker marker name, can be lower case, doesn't need the ### delimiters
     * @param string $content the marker's value
     */
    public function addMarker($marker, $content)
    {
        $this->markers['###' . strtoupper($marker) . '###'] = $content;
    }

    /**
     * Sets an array of markers with their values.
     *
     * @param array $markers array of markers
     */
    public function addMarkerArray(array $markers)
    {
        foreach ($markers as $marker => $content) {
            $this->addMarker($marker, $content);
        }
    }

    /**
     * Sets a subpart's value.
     *
     * @param string $subpartMarker subpart name, can be lower case, doesn't need the ### delimiters
     * @param string $content the subpart's value
     */
    public function addSubpart($subpartMarker, $content)
    {
        $this->subparts['###' . strtoupper($subpartMarker) . '###'] = $content;
    }

    /**
     * Assigns a variable to the html template.
     * Simple variables can be used like regular markers or in the form
     * VAR:"VARIABLE_NAME" (without the quotes). Objects can be used in the
     * form VAR:"OBJECT_NAME"."PROPERTY_NAME" (without the quotes).
     *
     * @param string $key variable key
     * @param mixed $value variable value
     */
    public function addVariable($key, $value)
    {
        $key = strtolower($key);

        $this->variables[$key] = $value;
    }

    /**
     * Adds a named loop. The given array is looped over in the template.
     *
     * @param string $loopName loop name
     * @param string $markerName
     * @param array $variables variables array
     */
    public function addLoop($loopName, $markerName, array $variables)
    {
        $this->loops[$loopName] = array(
            'marker' => $markerName,
            'data' => $variables
        );
    }

    /**
     * Gets a list of Markers from the selected subpart.
     *
     * @param string $template marker name
     * @param string $markerPrefix
     * @param bool $capturePrefix
     * @return array Array of markers
     */
    public function getMarkersFromTemplate(
        $template,
        $markerPrefix = '',
        $capturePrefix = true
    ) {
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
     * @param string $markerPrefix a prefix to limit the result to markers beginning with the specified prefix
     * @return array Array of markers names
     */
    public function findMarkers($markerPrefix = '')
    {
        return $this->getMarkersFromTemplate($this->workOnSubpart,
            $markerPrefix);
    }

    /**
     * Gets a list of view helper marker arguments for a given view helper from
     * the selected subpart.
     *
     * @param string $helperMarker marker name, can be lower case, doesn't need the ### delimiters
     * @param string $subpart subpart markup to search in
     * @param boolean $removeDuplicates Optionally determines whether duplicate view helpers are removed. Defaults to TRUE.
     * @return array Array of markers
     */
    protected function getViewHelperArgumentLists(
        $helperMarker,
        $subpart,
        $removeDuplicates = true
    ) {
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
     * @param string $content A string that should be searched for view helpers.
     * @return array A list of view helper names used in the template.
     */
    public function findViewHelpers($content)
    {
        preg_match_all('!###([\w]+):.*?\###!is', $content, $match);
        $viewHelpers = array_unique($match[1]);

        // remove / protect LOOP, LOOP_CONTENT subparts
        $loopIndex = array_search('LOOP', $viewHelpers);
        if ($loopIndex !== false) {
            unset($viewHelpers[$loopIndex]);
        }
        $loopContentIndex = array_search('LOOP_CONTENT', $viewHelpers);
        if ($loopContentIndex !== false) {
            unset($viewHelpers[$loopContentIndex]);
        }

        // remove / protect IF subparts
        $ifIndex = array_search('IF', $viewHelpers);
        if ($ifIndex !== false) {
            unset($viewHelpers[$ifIndex]);
        }

        return $viewHelpers;
    }

    /**
     * Gets a list of given markers from the selected subpart.
     *
     * @param string $variableMarker marker name, can be lower case, doesn't need the ### delimiters
     * @param string $subpart subpart name
     * @return array array of markers
     */
    public function getVariableMarkers($variableMarker, $subpart)
    {
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
    public function isVariableMarker($potentialVariableMarker)
    {
        $regex = '!###[A-Z0-9_-]*\.[A-Z0-9_-|:.]*\###!is';
        $isVariableMarker = preg_match($regex, $potentialVariableMarker);

        return (boolean)$isVariableMarker;
    }

    /**
     * @param $templateContent
     * @return mixed
     */
    public function setTemplateContent($templateContent)
    {
        return $this->template = $templateContent;
    }

    public function getTemplateContent()
    {
        return $this->template;
    }

    public function getWorkOnSubpart()
    {
        return $this->workOnSubpart;
    }

    /**
     * Sets the debug mode on or off.
     *
     * @param boolean $mode debug mode, TRUE to enable debug mode, FALSE to turn off again, off by default
     */
    public function setDebugMode($mode)
    {
        $this->debugMode = (boolean)$mode;
    }
}
