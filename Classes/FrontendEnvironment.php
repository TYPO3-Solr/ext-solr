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

use ApacheSolrForTypo3\Solr\FrontendEnvironment\TypoScript;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use Doctrine\DBAL\Exception as DBALException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
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
     * Loads the TypoScript configuration for a given page id and language.
     * Language usage may be disabled to get the default TypoScript
     * configuration.
     *
     * @throws DBALException
     */
    public function getConfigurationFromPageId(
        int $pageId,
        ?string $path = '',
        ?int $rootPageId = null
    ): TypoScriptConfiguration {
        return GeneralUtility::makeInstance(TypoScript::class)->getConfigurationFromPageId($pageId, $path, $rootPageId);
    }

    /**
     * Check whether the page record is within the configured allowed pages types(doktype) for indexing.
     * Uses TypoScript: plugin.tx_solr.index.queue.<queue name>.allowedPageTypes
     *
     * @throws DBALException
     */
    public function isAllowedPageType(
        array $pageRecord,
        ?string $configurationName = null,
    ): bool {
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

        $configuration = $this->getConfigurationFromPageId($rootPageRecordUid);
        if ($configurationName !== null) {
            $allowedPageTypes = $configuration->getIndexQueueAllowedPageTypesArrayByConfigurationName($configurationName);
        } else {
            // If the $configurationName is not provided,
            // we will check if one of the configurations allow the page type to be indexed
            $allowedPageTypes = $configuration->getAllIndexQueueAllowedPageTypesArray();
        }
        return in_array($pageRecord['doktype'], $allowedPageTypes);
    }

    /**
     * Returns TypoScriptConfiguration for desired page ID and language id.
     *
     * @throws DBALException
     */
    public function getSolrConfigurationFromPageId(
        int $pageId,
        ?int $rootPageId = null
    ): TypoScriptConfiguration {
        return $this->getConfigurationFromPageId($pageId, '', $rootPageId);
    }
}
