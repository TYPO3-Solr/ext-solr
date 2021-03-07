<?php

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

namespace ApacheSolrForTypo3\Solr;

use ApacheSolrForTypo3\Solr\FrontendEnvironment\Tsfe;
use ApacheSolrForTypo3\Solr\FrontendEnvironment\TypoScript;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FrontendEnvironment is responsible for initializing/simulating the frontend in backend context
 * For example on:
 * * indexing
 * * Status-report and other actions via TYPO3 backend
 * * etc.
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

    /**
     * FrontendEnvironment constructor.
     *
     * @param Tsfe|null $tsfe
     * @param TypoScript|null $typoScript
     */
    public function __construct(Tsfe $tsfe = null, TypoScript $typoScript = null)
    {
        $this->tsfe = $tsfe ?? GeneralUtility::makeInstance(Tsfe::class);
        $this->typoScript = $typoScript ?? GeneralUtility::makeInstance(TypoScript::class);
    }

    /**
     * Changes language context.
     * Should be used in indexing context.
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
     * @param $pageId
     * @param int $language
     * @throws SiteNotFoundException
     * @throws ServiceUnavailableException
     * @throws ImmediateResponseException
     */
    public function initializeTsfe($pageId, $language = 0)
    {
        $this->tsfe->initializeTsfe($pageId, $language);
    }

    /**
     * Loads the TypoScript configuration for a given page id and language.
     * Language usage may be disabled to get the default TypoScript
     * configuration.
     *
     * @param int $pageId
     * @param ?string $path
     * @param ?int $language
     * @return TypoScriptConfiguration
     */
    public function getConfigurationFromPageId($pageId, $path = '', $language = 0): TypoScriptConfiguration
    {
        return $this->typoScript->getConfigurationFromPageId($pageId, $path, $language);
    }

    /**
     * Check whether the page record is within the configured allowed pages types(doktype) for indexing.
     * Uses TypoScript: plugin.tx_solr.index.queue.<queue name>.allowedPageTypes
     *
     * @param array $pageRecord
     * @param ?string $configurationName
     * @return bool
     */
    public function isAllowedPageType(array $pageRecord, $configurationName = 'pages'): bool
    {
        $configuration = $this->getConfigurationFromPageId($pageRecord['uid'], '');
        $allowedPageTypes = $configuration->getIndexQueueAllowedPageTypesArrayByConfigurationName($configurationName);
        return in_array($pageRecord['doktype'], $allowedPageTypes);
    }

    /**
     * Returns TypoScriptConfiguration for desired page ID and language id.
     *
     * @param int $pageId
     * @param ?int $language
     * @return TypoScriptConfiguration
     */
    public function getSolrConfigurationFromPageId($pageId, $language = 0): TypoScriptConfiguration
    {
        return $this->getConfigurationFromPageId($pageId, '', $language);
    }
}