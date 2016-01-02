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

use ApacheSolrForTypo3\Solr\LanguageFileUnavailableException;
use TYPO3\CMS\Core\FormProtection\Exception;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * view helper to replace label markers starting with "LLL:"
 * Replaces viewhelpers ###LLL:languageKey###
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Lll implements ViewHelper
{

    /**
     * Path to a label file
     *
     * @var string
     */
    protected $languageFile;

    /**
     * Language key, f.e. default, en, de, fr, ...
     *
     * @var string
     */
    protected $llKey;

    /**
     * Cache for loaded labels
     *
     * @var array
     */
    protected $localLang;

    /**
     * An instance of the localization factory
     *
     * @var \TYPO3\CMS\Core\Localization\LocalizationFactory
     */
    protected $languageFactory;

    /**
     * Constructor
     *
     * @param array $arguments
     * @throws \ApacheSolrForTypo3\Solr\LanguageFileUnavailableException
     */
    public function __construct(array $arguments = array())
    {
        if (!isset($arguments['languageFile'])) {
            throw new LanguageFileUnavailableException(
                'No Language File given',
                1234972358
            );
        }
        $this->languageFactory = GeneralUtility::makeInstance('TYPO3\CMS\Core\Localization\LocalizationFactory');
        $this->languageFile = $arguments['languageFile'];
        $this->llKey = $arguments['llKey'];

        $this->loadLL();
    }

    /**
     * Loads the initially defined local lang file
     *
     * @return void
     */
    protected function loadLL()
    {
        $configuration = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_solr.'];

        $this->localLang[$this->languageFile] = $this->languageFactory->getParsedData(
            $this->languageFile,
            $this->llKey,
            $GLOBALS['TSFE']->renderCharset,
            3
        );

        // Overlaying labels from TypoScript (including fictitious language keys for non-system languages!):
        if (is_array($configuration['_LOCAL_LANG.'])) {
            foreach ($configuration['_LOCAL_LANG.'] as $language => $overrideLabels) {
                $language = substr($language, 0, -1);

                if (is_array($overrideLabels)) {
                    foreach ($overrideLabels as $labelKey => $overrideLabel) {
                        if (!is_array($overrideLabel)) {
                            $this->localLang[$this->languageFile][$language][$labelKey] = array(
                                array(
                                    'source' => $overrideLabel
                                )
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns a label for the given key
     *
     * @param array $arguments
     * @return string
     */
    public function execute(array $arguments = array())
    {
        $label = '';

        $isFullPath = false;
        if (GeneralUtility::isFirstPartOfStr($arguments[0], 'FILE')) {
            $arguments[0] = substr($arguments[0], 5);
            $isFullPath = true;
        }

        if ($isFullPath || GeneralUtility::isFirstPartOfStr($arguments[0],
                'EXT')
        ) {
            // a full path reference...
            $label = $this->resolveFullPathLabel($arguments[0]);
        } else {
            $label = $this->getLabel($this->languageFile, $arguments[0]);
        }

        return $label;
    }

    /**
     * Resolves a label given through a full LLL path by loading the specified
     * local lang file and then returning the requested label.
     *
     * @param string $path full path specifying a label, LLL:EXT:path/to/locallang.xml:my_label
     * @return string the requested label
     */
    protected function resolveFullPathLabel($path)
    {
        $pathParts = explode(':', $path);

        $labelKey = array_pop($pathParts);
        $path = GeneralUtility::getFileAbsFileName(implode(':', $pathParts));

        if (!isset($this->localLang[$path])) {
            // do some nice caching
            $this->localLang[$path] = GeneralUtility::readLLfile(
                $path,
                $this->llKey,
                $GLOBALS['TSFE']->renderCharset
            );
        }

        return $this->getLabel($path, $labelKey);
    }

    /**
     * Gets a label from the already loaded and cached labels
     *
     * @param string $locallang key for a local lang file
     * @param string $labelKey label key
     * @return string requested label in the current language if available, in default language otherwise
     */
    protected function getLabel($locallang, $labelKey)
    {
        $label = '';

        if (!empty($this->localLang[$locallang][$this->llKey][$labelKey])) {
            $label = $this->localLang[$locallang][$this->llKey][$labelKey];
        } else {
            $label = $this->localLang[$locallang]['default'][$labelKey];
        }


        // TYPO3 4.6 workaround until we support xliff
        if (is_array($label)) {
            if (!empty($label[0]['target'])) {
                $label = $label[0]['target'];
            } else {
                $label = $label[0]['source'];
            }
        }

        return $label;
    }
}
