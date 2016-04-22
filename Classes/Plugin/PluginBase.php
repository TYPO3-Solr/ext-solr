<?php
namespace ApacheSolrForTypo3\Solr\Plugin;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@aoemedia.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\JavascriptManager;
use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Search;
use ApacheSolrForTypo3\Solr\Template;
use ApacheSolrForTypo3\Solr\ViewHelper\ViewHelperProvider;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;

/**
 * Abstract base class for all solr plugins.
 *
 * Implements a main method and several abstract methods which
 * need to be implemented by an inheriting plugin.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Timo Schmidt <timo.schmidt@aoemedia.de>
 * @package TYPO3
 * @subpackage solr
 */
abstract class PluginBase extends AbstractPlugin
{

    public $prefixId = 'tx_solr';
    public $extKey = 'solr';

    /**
     * an instance of ApacheSolrForTypo3\Solr\Search
     *
     * @deprecated use $this->searchResultsSetService()->getSearch() instead, will be removed in version 5.0
     * @var Search
     */
    protected $search;

    /**
     * The plugin's query
     *
     * @deprecated use $this->searchResultSet->getUsedQuery() instead, will be removed in version 5.0
     * @var Query
     */
    protected $query = null;

    /**
     * Determines whether the solr server is available or not.
     *
     * @deprecated use $this->searchResultsSetService()->getIsSolrAvailable() instead, will be removed in version 5.0
     */
    protected $solrAvailable;

    /**
     * An instance of ApacheSolrForTypo3\Solr\Template
     *
     * @var Template
     */
    protected $template;

    /**
     * An instance of ApacheSolrForTypo3\Solr\JavascriptManager
     *
     * @var JavascriptManager
     */
    protected $javascriptManager;

    /**
     * An instance of the localization factory
     *
     * @var \TYPO3\CMS\Core\Localization\LocalizationFactory
     */
    protected $languageFactory;

    /**
     * The user's raw query.
     *
     * Private to enforce API usage.
     *
     * @var string
     */
    private $rawUserQuery;

    // Main

    /**
     * @var TypoScriptConfiguration
     */
    public $typoScriptConfiguration;

    /**
     * @var SearchResultSetService
     */
    private $searchResultsSetService;

    /**
     * The main method of the plugin
     *
     * @param string $content The plugin content
     * @param array $configuration The plugin configuration
     * @return string The content that is displayed on the website
     */
    public function main($content, $configuration)
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $content = '';

        try {
            $this->initialize($configuration);
            $this->preRender();

            $actionResult = $this->performAction();

            if ($this->getSearchResultSetService()->getIsSolrAvailable()) {
                $content = $this->render($actionResult);
            } else {
                $content = $this->renderError();
            }

            $content = $this->postRender($content);
        } catch (\Exception $e) {
            if ($this->typoScriptConfiguration->getLoggingExceptions()) {
                GeneralUtility::devLog(
                    $e->getCode() . ': ' . $e->__toString(),
                    'solr',
                    3,
                    (array)$e
                );
            }

            $this->initializeTemplateEngine();
            $content = $this->renderException();
        }

        return $this->baseWrap($content);
    }

    /**
     * Adds the possibility to use stdWrap on the plugins content instead of wrapInBaseClass.
     * Defaults to wrapInBaseClass to ensure downward compatibility.
     *
     * @param string $content The plugin content
     * @return string
     */
    protected function baseWrap($content)
    {
        if (isset($this->conf['general.']['baseWrap.'])) {
            return $this->cObj->stdWrap($content,
                $this->conf['general.']['baseWrap.']);
        } else {
            return $this->pi_wrapInBaseClass($content);
        }
    }

    /**
     * Implements the action logic. The result of this method is passed to the
     * render method.
     *
     * @return string Action result
     */
    abstract protected function performAction();


    // Initialization


    /**
     * Initializes the plugin - configuration, language, caching, search...
     *
     * @param array $configuration configuration array as provided by the TYPO3 core
     */
    protected function initialize($configuration)
    {
        /** @var $configurationManager \ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager */
        $configurationManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\System\\Configuration\\ConfigurationManager');
        $typoScriptConfiguration = $configurationManager->getTypoScriptConfiguration()->mergeSolrConfiguration($configuration);
        $this->typoScriptConfiguration = $typoScriptConfiguration;

        $this->initializeLanguageFactory();
        $this->pi_setPiVarDefaults();
        $this->pi_loadLL();
        $this->pi_initPIflexForm();


        $this->overrideTyposcriptWithFlexformSettings();

        $this->initializeQuery();
        $this->initializeSearch();
        $this->initializeTemplateEngine();
        $this->initializeJavascriptManager();

        $this->postInitialize();
    }

    /**
     * Overwrites pi_setPiVarDefaults to add stdWrap-functionality to _DEFAULT_PI_VARS
     *
     * @author Grigori Prokhorov <grigori.prokhorov@dkd.de>
     * @author Ivan Kartolo <ivan.kartolo@dkd.de>
     * @return void
     */
    public function pi_setPiVarDefaults()
    {
        if (is_array($this->conf['_DEFAULT_PI_VARS.'])) {
            foreach ($this->conf['_DEFAULT_PI_VARS.'] as $key => $defaultValue) {
                $this->conf['_DEFAULT_PI_VARS.'][$key] = $this->cObj->cObjGetSingle($this->conf['_DEFAULT_PI_VARS.'][$key],
                    $this->conf['_DEFAULT_PI_VARS.'][$key . '.']);
            }

            $piVars = is_array($this->piVars) ? $this->piVars : array();
            $this->piVars = $this->conf['_DEFAULT_PI_VARS.'];
            ArrayUtility::mergeRecursiveWithOverrule(
                $this->piVars,
                $piVars
            );
        }
    }

    /**
     * Overwrites pi_loadLL() to handle custom location of language files.
     *
     * Loads local-language values by looking for a "locallang" file in the
     * plugin class directory ($this->scriptRelPath) and if found includes it.
     * Also locallang values set in the TypoScript property "_LOCAL_LANG" are
     * merged onto the values found in the "locallang" file.
     * Supported file extensions xlf, xml, php
     * 
     * @param string $languageFilePath path to the plugin language file in format EXT:....
     * @return void
     */
    public function pi_loadLL($languageFilePath = '')
    {
        if (!$this->LOCAL_LANG_loaded && $this->scriptRelPath) {
            $pathElements = pathinfo($this->scriptRelPath);
            $languageFileName = $pathElements['filename'];

            $basePath = 'EXT:' . $this->extKey . '/Resources/Private/Language/Plugin' . $languageFileName . '/locallang.xlf';
            // Read the strings in the required charset (since TYPO3 4.2)
            $this->LOCAL_LANG = $this->languageFactory->getParsedData($basePath,
                $this->LLkey, $GLOBALS['TSFE']->renderCharset, 3);

            $alternativeLanguageKeys = GeneralUtility::trimExplode(',',
                $this->altLLkey, true);
            foreach ($alternativeLanguageKeys as $languageKey) {
                $tempLL = $this->languageFactory->getParsedData($basePath, $languageKey, $GLOBALS['TSFE']->renderCharset, 3);
                if ($this->LLkey !== 'default' && isset($tempLL[$languageKey])) {
                    $this->LOCAL_LANG[$languageKey] = $tempLL[$languageKey];
                }
            }
            // Overlaying labels from TypoScript (including fictitious language keys for non-system languages!):
            $translationInTypoScript = $this->typoScriptConfiguration->getLocalLangConfiguration();

            if (count($translationInTypoScript) == 0) {
                return;
            }

            // Clear the "unset memory"
            $this->LOCAL_LANG_UNSET = array();
            foreach ($translationInTypoScript as $languageKey => $languageArray) {
                // Remove the dot after the language key
                $languageKey = substr($languageKey, 0, -1);
                // Don't process label if the language is not loaded
                if (is_array($languageArray) && isset($this->LOCAL_LANG[$languageKey])) {
                    foreach ($languageArray as $labelKey => $labelValue) {
                        if (!is_array($labelValue)) {
                            $this->LOCAL_LANG[$languageKey][$labelKey][0]['target'] = $labelValue;
                            $this->LOCAL_LANG_charset[$languageKey][$labelKey] = 'utf-8';

                            if ($labelValue === '') {
                                $this->LOCAL_LANG_UNSET[$languageKey][$labelKey] = '';
                            }
                        }
                    }
                }
            }
        }
        $this->LOCAL_LANG_loaded = 1;
    }

    /**
     * Allows to override TypoScript settings with Flexform values.
     *
     */
    protected function overrideTyposcriptWithFlexformSettings()
    {
    }

    /**
     * Initializes the query from the GET query parameter.
     *
     */
    protected function initializeQuery()
    {
        $this->rawUserQuery = GeneralUtility::_GET('q');
    }

    /**
     * Initializes the Solr connection and tests the connection through a ping.
     *
     */
    protected function initializeSearch()
    {
        $solrConnection = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager')->getConnectionByPageId(
            $GLOBALS['TSFE']->id,
            $GLOBALS['TSFE']->sys_language_uid,
            $GLOBALS['TSFE']->MP
        );

        $search = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Search', $solrConnection);
        /** @var $this->searchService ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService */
        $this->searchResultsSetService = GeneralUtility::makeInstance('ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\SearchResultSetService', $this->typoScriptConfiguration, $search, $this);
        $this->solrAvailable = $this->searchResultsSetService->getIsSolrAvailable();
        $this->search = $this->searchResultsSetService->getSearch();
    }

    /**
     * @return SearchResultSetService
     */
    public function getSearchResultSetService()
    {
        return $this->searchResultsSetService;
    }

    /**
     * Initializes the template engine and returns the initialized instance.
     *
     * @return Template
     * @throws \UnexpectedValueException if a view helper provider fails to implement interface ApacheSolrForTypo3\Solr\ViewHelper\ViewHelperProvider
     */
    protected function initializeTemplateEngine()
    {
        $templateFile = $this->getTemplateFile();
        $subPart = $this->getSubpart();

        $flexformTemplateFile = $this->pi_getFFvalue(
            $this->cObj->data['pi_flexform'],
            'templateFile',
            'sOptions'
        );
        if (!empty($flexformTemplateFile)) {
            $templateFile = $flexformTemplateFile;
        }

        /** @var Template $template */
        $template = GeneralUtility::makeInstance(
            'ApacheSolrForTypo3\\Solr\\Template',
            $this->cObj,
            $templateFile,
            $subPart
        );
        $template->addViewHelperIncludePath($this->extKey,
            'Classes/ViewHelper/');
        $template->addViewHelper('LLL', array(
            'languageFile' => 'EXT:solr/Resources/Private/Language/' . str_replace('Pi',
                    'Plugin', $this->getPluginKey()) . '/locallang.xlf',
            'llKey' => $this->LLkey
        ));


        // can be used for view helpers that need configuration during initialization
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr'][$this->getPluginKey()]['addViewHelpers'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr'][$this->getPluginKey()]['addViewHelpers'] as $classReference) {
                $viewHelperProvider = &GeneralUtility::getUserObj($classReference);

                if ($viewHelperProvider instanceof ViewHelperProvider) {
                    $viewHelpers = $viewHelperProvider->getViewHelpers();
                    foreach ($viewHelpers as $helperName => $helperObject) {
                        // TODO check whether $helperAdded is TRUE, throw an exception if not
                        $helperAdded = $template->addViewHelperObject($helperName,
                            $helperObject);
                    }
                } else {
                    throw new \UnexpectedValueException(
                        get_class($viewHelperProvider) . ' must implement interface ApacheSolrForTypo3\Solr\ViewHelper\ViewHelperProvider',
                        1310387296
                    );
                }
            }
        }

        $template = $this->postInitializeTemplateEngine($template);

        $this->template = $template;
    }

    /**
     * Initializes the javascript manager.
     *
     */
    protected function initializeJavascriptManager()
    {
        $this->javascriptManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\JavascriptManager');
    }

    /**
     * Initializes the language factory;
     */
    protected function initializeLanguageFactory()
    {
        $this->languageFactory = GeneralUtility::makeInstance('TYPO3\CMS\Core\Localization\LocalizationFactory');
    }

    /**
     * This method is called after initializing in the initialize method.
     * Overwrite this method to do your own initialization.
     *
     * @return void
     */
    protected function postInitialize()
    {
    }

    /**
     * Overwrite this method to do own initialisations  of the template.
     *
     * @param Template $template Template
     * @return Template
     */
    protected function postInitializeTemplateEngine(Template $template)
    {
        return $template;
    }


    // Rendering


    /**
     * This method executes the requested commands and applies the changes to
     * the template.
     *
     * @param $actionResult
     * @return string Rendered plugin content
     */
    abstract protected function render($actionResult);

    /**
     * Renders a solr error.
     *
     * @return string A representation of the error that should be understandable for the user.
     */
    protected function renderError()
    {
        $this->template->workOnSubpart('solr_search_unavailable');

        return $this->template->render();
    }

    /**
     * Renders a solr exception.
     *
     * @return string A representation of the exception that should be understandable for the user.
     */
    protected function renderException()
    {
        $this->template->workOnSubpart('solr_search_error');

        return $this->template->render();
    }

    /**
     * Should be overwritten to do things before rendering.
     *
     */
    protected function preRender()
    {
    }

    /**
     * Overwrite this method to perform changes to the content after rendering.
     *
     * @param string $content The content rendered by the plugin so far
     * @return string The content that should be presented on the website, might be different from the output rendered before
     */
    protected function postRender($content)
    {
        if (isset($this->conf['stdWrap.'])) {
            $content = $this->cObj->stdWrap($content, $this->conf['stdWrap.']);
        }

        return $content;
    }


    // Helper methods


    /**
     * Determines the template file from the configuration.
     *
     * Overwrite this method to use a different template.
     *
     * @return string The template file name to be used for the plugin
     */
    protected function getTemplateFile()
    {
        return $this->typoScriptConfiguration->getTemplateByFileKey($this->getTemplateFileKey());
    }

    /**
     * This method should be implemented to return the TSconfig key which
     * contains the template name for this template.
     *
     * @see initializeTemplateEngine()
     * @return string The TSconfig key containing the template name
     */
    abstract protected function getTemplateFileKey();

    /**
     * Gets the plugin's template instance.
     *
     * @return Template The plugin's template.
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Gets the plugin's javascript manager.
     *
     * @return JavascriptManager The plugin's javascript manager.
     */
    public function getJavascriptManager()
    {
        return $this->javascriptManager;
    }

    /**
     * Should return the relevant subpart of the template.
     *
     * @see initializeTemplateEngine()
     * @return string The subpart of the template to be used
     */
    abstract protected function getSubpart();

    /**
     * This method should return the plugin key. Reads some configuration
     * options in initializeTemplateEngine()
     *
     * @see initializeTemplateEngine()
     * @return string The plugin key
     */
    abstract protected function getPluginKey();

    /**
     * Gets the target page Id for links. Might have been set through either
     * flexform or TypoScript. If none is set, TSFE->id is used.
     *
     * @return integer The page Id to be used for links
     */
    public function getLinkTargetPageId()
    {
        return $this->typoScriptConfiguration->getSearchTargetPage();
    }

    /**
     * Gets the ApacheSolrForTypo3\Solr\Search instance used for the query. Mainly used as a
     * helper function for result document modifiers.
     *
     * @deprecated use $this->getSearchResultSetService()->getSearch() instead, will be removed in version 5.0
     * @return Search
     */
    public function getSearch()
    {
        GeneralUtility::logDeprecatedFunction();
        return $this->search;
    }

    /**
     * Sets the ApacheSolrForTypo3\Solr\Search instance used for the query. Mainly used as a
     * helper function for result document modifiers.
     *
     * @deprecated should not be set able from outside, will be removed in version 5.0
     * @param Search $search Search instance
     */
    public function setSearch(Search $search)
    {
        GeneralUtility::logDeprecatedFunction();
        $this->search = $search;
    }

    /**
     * Gets the user's query term and cleans it so that it can be used in
     * templates f.e.
     *
     * @return string The cleaned user query.
     */
    public function getCleanUserQuery()
    {
        $userQuery = $this->getRawUserQuery();

        if (!is_null($userQuery)) {
            $userQuery = Query::cleanKeywords($userQuery);
        }

        // escape triple hashes as they are used in the template engine
        // TODO remove after switching to fluid templates
        $userQuery = Template::escapeMarkers($userQuery);

        return $userQuery;
    }

    /**
     * Gets the raw user query
     *
     * @return string Raw user query.
     */
    public function getRawUserQuery()
    {
        return $this->rawUserQuery;
    }

    /**
     * @return string
     */
    protected function getCurrentUrlWithQueryLinkBuilder()
    {
        $currentUrl = $this->pi_linkTP_keepPIvars_url();
        $resultService = $this->getSearchResultSetService();

        if (!$resultService instanceof SearchResultSetService) {
            return $currentUrl;
        }

        if ($resultService->getIsSolrAvailable() && $this->getSearchResultSetService()->getHasSearched()) {
            $queryLinkBuilder = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Query\\LinkBuilder', $this->getSearchResultSetService()->getSearch()->getQuery());
            $currentUrl = $queryLinkBuilder->getQueryUrl();
            return $currentUrl;
        }
        return $currentUrl;
    }
}
