<?php
namespace ApacheSolrForTypo3\Solr;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use ApacheSolrForTypo3\Solr\FrontendEnvironment\Tsfe;
use ApacheSolrForTypo3\Solr\FrontendEnvironment\TypoScript;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Frontend environment
 *
 * @author Timo Hund <timo.hund@dkd.de>
 * @copyright (c) 2020-2021 Timo Schmidt <timo.schmidt@dkd.de>
 */
class FrontendEnvironment implements SingletonInterface
{
    /**
     * @var TypoScript
     */
    private $typoScript = null;

    /**
     * @var Tsfe
     */
    private $tsfe = null;

    public function __construct(Tsfe $tsfe = null, TypoScript $typoScript = null)
    {
        $this->tsfe = $tsfe ?? GeneralUtility::makeInstance(Tsfe::class);
        $this->typoScript = $typoScript ?? GeneralUtility::makeInstance(TypoScript::class);
    }

    /**
     * Change the language context.
     *
     * @param int $pageId
     * @param int $language
     */
    public function changeLanguageContext(int $pageId, int $language): void
    {
        $this->tsfe->changeLanguageContext($pageId, $language);
    }

    /**
     * Initializes the TSFE for a given page ID and language.
     *
     * @param int $pageId
     * @param int $language
     * @throws SiteNotFoundException
     * @throws \TYPO3\CMS\Core\Error\Http\ServiceUnavailableException
     * @throws \TYPO3\CMS\Core\Http\ImmediateResponseException
     */
    public function initializeTsfe($pageId, $language = 0)
    {
        $this->tsfe->initializeTsfe($pageId, $language);
    }

    /**
     * Get the configuration for a given page id, typoscript path and language.
     *
     * @param int $pageId
     * @param string $path
     * @param int $language
     * @return System\Configuration\TypoScriptConfiguration
     */
    public function getConfigurationFromPageId($pageId, $path, $language = 0)
    {
        return $this->typoScript->getConfigurationFromPageId($pageId, $path, $language);
    }

    /**
     * Check if given record is allowed for the configuration name
     *
     * @param array $pageRecord
     * @param string $configurationName
     * @return bool
     */
    public function isAllowedPageType(array $pageRecord, $configurationName = 'pages'): bool
    {
        $configuration = $this->getConfigurationFromPageId($pageRecord['uid'], '');
        $allowedPageTypes = $configuration->getIndexQueueAllowedPageTypesArrayByConfigurationName($configurationName);
        return in_array($pageRecord['doktype'], $allowedPageTypes);
    }

    /**
     * Returns the Solr configuration for a given page and language
     *
     * @param int $pageId
     * @param int $language
     * @return System\Configuration\TypoScriptConfiguration
     */
    public function getSolrConfigurationFromPageId($pageId, $language = 0)
    {
        return $this->getConfigurationFromPageId($pageId, '', $language);
    }
}
