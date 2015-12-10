<?php
namespace ApacheSolrForTypo3\Solr;

/***************************************************************
 *  Copyright notice
 *
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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Manger for the javascript files used throughout the extension's plugins.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class JavascriptManager
{

    const POSITION_HEADER = 'header';
    const POSITION_FOOTER = 'footer';
    const POSITION_NONE = 'none';
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
     * Javascript file configuration.
     *
     * @var array
     */
    protected $configuration;
    /**
     * Where to insert the JS, either header or footer
     *
     * @var string
     */
    protected $javascriptInsertPosition;
    /**
     * JavaScript tags to add to the page for the current instance
     *
     * @var array
     */
    protected $javaScriptTags = array();


    /**
     * Constructor.
     *
     */
    public function __construct()
    {
        $this->configuration = Util::getSolrConfiguration();
    }

    /**
     * Adds a Javascript snippet.
     *
     * @param string $identifier Identifier for the snippet.
     * @param string $snippet The snippet to add.
     */
    public function addJavascript($identifier, $snippet)
    {
        if (!array_key_exists($identifier, self::$snippets)) {
            self::$snippets[$identifier] = array(
                'addedToPage' => false,
                'snippet' => $snippet
            );
        }
    }

    /**
     * Loads a file by its key as defined in plugin.tx_solr.javascriptFiles.
     *
     * @param string $fileKey Key of the file to load.
     */
    public function loadFile($fileKey)
    {
        if (!array_key_exists($fileKey, self::$files)) {
            $typoScriptPath = 'plugin.tx_solr.javascriptFiles.' . $fileKey;
            $fileReference = Util::getTypoScriptValue($typoScriptPath);

            if (!empty($fileReference)) {
                self::$files[$fileKey] = array(
                    'addedToPage' => false,
                    'file' => GeneralUtility::createVersionNumberedFilename($GLOBALS['TSFE']->tmpl->getFileName($fileReference))
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
    public function addJavascriptToPage()
    {
        $position = Util::getTypoScriptValue('plugin.tx_solr.javascriptFiles.loadIn');

        if (empty($position)) {
            $position = self::POSITION_NONE;
        }

        switch ($position) {
            case self::POSITION_HEADER:
                $this->addJavascriptToPageHeader();
                break;
            case self::POSITION_FOOTER:
                $this->registerForRenderPreProcessHook();
                break;
            case self::POSITION_NONE:
                // do nothing, JS is handled by the integrator
                break;
            default:
                throw new \RuntimeException(
                    'Invalid value "' . $position . '" for Javascript position. Choose from "header", "footer", or "none".',
                    1336911986
                );
        }
    }

    /**
     * Adds all the loaded javascript files and snippets to the page header.
     *
     */
    protected function addJavascriptToPageHeader()
    {
        $this->javascriptInsertPosition = self::POSITION_HEADER;
        $this->buildJavascriptTags();
    }

    /**
     * Builds the tags to load the javascript needed for different features.
     *
     */
    public function buildJavascriptTags()
    {
        $filePathPrefix = '';
        if (!empty($GLOBALS['TSFE']->config['config']['absRefPrefix'])) {
            $filePathPrefix = $GLOBALS['TSFE']->config['config']['absRefPrefix'];
            if ($filePathPrefix === 'auto') {
                $filePathPrefix = GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
            }
        }

        // add files
        foreach (self::$files as $identifier => $file) {
            if (!$file['addedToPage']) {
                self::$files[$identifier]['addedToPage'] = true;

                $this->addJsFile($filePathPrefix . $file['file']);
            }
        }

        // concatenate snippets
        $snippets = '';
        foreach (self::$snippets as $identifier => $snippet) {
            if (!$snippet['addedToPage']) {
                self::$snippets[$identifier]['addedToPage'] = true;

                $snippets .= "\t/* -- $identifier -- */\n";
                $snippets .= $snippet['snippet'];
                $snippets .= "\n\n";
            }
        }

        // add snippets
        if (!empty($snippets)) {
            $this->addJsInline($snippets);
        }
    }

    /**
     * Adds a JavaScript file to the page
     *
     * @param string $file File path
     */
    protected function addJsFile($file)
    {
        if ($this->javascriptInsertPosition == self::POSITION_HEADER) {
            $this->getPageRenderer()->addJsFile($file);
        } else {
            $this->getPageRenderer()->addJsFooterFile($file);
        }
    }

    /**
     * Adds a JavaScript snippet to the page
     *
     * @param string $snippet JS snippet
     */
    protected function addJsInline($snippet)
    {
        if ($this->javascriptInsertPosition == self::POSITION_HEADER) {
            $this->getPageRenderer()->addJsInlineCode('tx_solr-javascript-inline',
                $snippet);
        } else {
            $this->getPageRenderer()->addJsFooterInlineCode('tx_solr-javascript-inline',
                $snippet);
        }
    }

    /**
     * @return \TYPO3\CMS\Core\Page\PageRenderer
     */
    protected function getPageRenderer()
    {
        return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Page\PageRenderer');
    }

    /**
     * Registers the Javascript Manager to be called when the page is rendered
     * so that the Javascript can be added at the end of the page.
     *
     */
    protected function registerForRenderPreProcessHook()
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_pagerenderer.php']['render-preProcess']['tx_solr-javascript'] = 'ApacheSolrForTypo3\Solr\JavascriptManager->addJavascriptToPageFooter';
    }

    /**
     * Adds all the loaded javascript files and snippets to the page footer.
     *
     */
    public function addJavascriptToPageFooter()
    {
        $this->javascriptInsertPosition = self::POSITION_FOOTER;
        $this->buildJavascriptTags();
    }
}
