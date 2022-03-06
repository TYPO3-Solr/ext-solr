<?php

declare(strict_types=1);

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
use Doctrine\DBAL\Driver\Exception as DBALDriverException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

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
     * Loads the TypoScript configuration for a given page id and language.
     * Language usage may be disabled to get the default TypoScript
     * configuration.
     *
     * @param int $pageId
     * @param ?string $path
     * @param ?int $language
     * @param int|null $rootPageId
     * @return TypoScriptConfiguration
     * @throws DBALDriverException
     */
    public function getConfigurationFromPageId(int $pageId, ?string $path = '', ?int $language = 0, ?int $rootPageId = null): TypoScriptConfiguration
    {
        return GeneralUtility::makeInstance(TypoScript::class)->getConfigurationFromPageId($pageId, $path, $language, $rootPageId);
    }

    /**
     * Check whether the page record is within the configured allowed pages types(doktype) for indexing.
     * Uses TypoScript: plugin.tx_solr.index.queue.<queue name>.allowedPageTypes
     *
     * @param array $pageRecord
     * @param ?string $configurationName
     * @return bool
     * @throws DBALDriverException
     */
    public function isAllowedPageType(array $pageRecord, ?string $configurationName = 'pages'): bool
    {
        // $pageRecord could come from DataHandler and with all columns. So we want to fetch it again.
        $pageRecord = BackendUtility::getRecord('pages', $pageRecord['uid']);
        $rootPageRecordUid = $pageRecord['uid'];
        if (isset($pageRecord['sys_language_uid'])
            && (int)$pageRecord['sys_language_uid'] > 0
            && isset($pageRecord['l10n_parent'])
            && (int)$pageRecord['l10n_parent'] > 0
        ) {
            $rootPageRecordUid = $pageRecord['l10n_parent'];
        }

        $tsfe = GeneralUtility::makeInstance(Tsfe::class)->getTsfeByPageIdIgnoringLanguage($rootPageRecordUid);
        if (!$tsfe instanceof TypoScriptFrontendController) {
            return false;
        }
        $configuration = $this->getConfigurationFromPageId($rootPageRecordUid, '', $tsfe->getLanguage()->getLanguageId());
        $allowedPageTypes = $configuration->getIndexQueueAllowedPageTypesArrayByConfigurationName($configurationName);
        return in_array($pageRecord['doktype'], $allowedPageTypes);
    }

    /**
     * Returns TypoScriptConfiguration for desired page ID and language id.
     *
     * @param int $pageId
     * @param ?int $language
     * @param int|null $rootPageId
     * @return TypoScriptConfiguration
     * @throws DBALDriverException
     */
    public function getSolrConfigurationFromPageId(int $pageId, ?int $language = 0, ?int $rootPageId = null): TypoScriptConfiguration
    {
        return $this->getConfigurationFromPageId($pageId, '', $language, $rootPageId);
    }
}
