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

use ApacheSolrForTypo3\Solr\System\Configuration\ConfigurationManager;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;
use ApacheSolrForTypo3\Solr\System\Logging\SolrLogManager;
use Doctrine\DBAL\Exception as DBALException;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LogLevel;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
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
     * Check whether the page record is within the configured allowed pages types(doktype) for indexing.
     * Uses TypoScript: plugin.tx_solr.index.queue.<queue name>.allowedPageTypes
     *
     * A page that does not exist (any more) or whose language does not exist on its site can not be
     * indexed, so false is returned for it.
     *
     * @throws DBALException
     * @throws JsonException
     * @throws NoSuchCacheException
     * @throws SiteNotFoundException
     */
    public function isAllowedPageType(
        array $pageRecord,
        ?string $configurationName = null,
    ): bool {
        // $pageRecord could come from DataHandler and with all columns. So we want to fetch it again.
        $pageRecord = BackendUtility::getRecord('pages', (int)($pageRecord['uid'] ?? 0));
        if ($pageRecord === null) {
            return false;
        }
        $rootPageRecordUid = $pageRecord['uid'];
        if (isset($pageRecord['sys_language_uid'])
            && (int)$pageRecord['sys_language_uid'] > 0
            && isset($pageRecord['l10n_parent'])
            && (int)$pageRecord['l10n_parent'] > 0
        ) {
            $rootPageRecordUid = $pageRecord['l10n_parent'];
        }

        // @todo: ensure that the fallbacks for the Site Language are checked.
        $languageId = (int)$pageRecord['sys_language_uid'];
        if ($languageId < 0) {
            $languageId = 0;
        }
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        try {
            $configuration = $configurationManager->getTypoScriptConfiguration($rootPageRecordUid, $languageId);
        } catch (InvalidArgumentException $e) {
            // Thrown by Site::getLanguageById() if the language of the page does not exist on its site,
            // e.g. because it was removed from the site configuration
            if ($e->getCode() !== 1522960188) {
                throw $e;
            }
            GeneralUtility::makeInstance(SolrLogManager::class, __CLASS__)->log(
                LogLevel::WARNING,
                'Language ' . $languageId . ' of page ' . $pageRecord['uid'] . ' does not exist on its site',
                [
                    'pageUid' => $pageRecord['uid'],
                    'languageId' => $languageId,
                    'error' => [
                        'code' => $e->getCode(),
                        'file' => $e->getFile() . ':' . $e->getLine(),
                        'message' => $e->getMessage(),
                    ],
                ],
            );
            return false;
        }
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
     *
     * @throws DBALException
     * @throws JsonException
     * @throws NoSuchCacheException
     * @throws SiteNotFoundException
     *
     * @todo: check when to use $rootPageId and if it can be removed.
     *   Most probably that belongs to mounted pages or to plugin.tx_solr.index.queue.[indexConfig].additionalPageIds
     *   For both cases, the indexing configuration must be used from desired/current root page given by `tx_solr_indexqueue_item`.`root`.
     *   Note about Site-config languages mismatch troubles: https://github.com/TYPO3-Solr/ext-solr/issues/3325#issuecomment-1900091020
     */
    public function getSolrConfigurationFromPageId(
        int $pageId,
        ?int $language = 0,
        ?int $rootPageId = null,
    ): TypoScriptConfiguration {
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        return $configurationManager->getTypoScriptConfiguration($pageId, $language);
    }
}
