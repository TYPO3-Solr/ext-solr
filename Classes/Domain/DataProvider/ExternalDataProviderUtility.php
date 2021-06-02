<?php
declare(strict_types=1);
namespace ApacheSolrForTypo3\Solr\Domain\DataProvider;

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


use ApacheSolrForTypo3\Solr\Domain\Index\ExternalDataProviderInterface;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class is in use to retrieve the configured data provider.
 *
 * @author Lars Tode <lars.tode@dkd.de>
 */
class ExternalDataProviderUtility implements SingletonInterface
{
    /**
     * @var ExternalDataProviderUtility
     */
    protected static $instance = null;

    /**
     * Only create one instance of this class
     *
     * @return ExternalDataProviderUtility
     */
    public static function getInstance(): ExternalDataProviderUtility
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Returns the data provider configured for a given table within the indexer or null
     *
     * @param string $tableName
     * @param int $rootPageId
     * @return ExternalDataProviderInterface|null
     */
    public function getProviderForIndexQueueByTableName(
        string $tableName,
        int $rootPageId
    ): ?ExternalDataProviderInterface {
        $configuration = Util::getSolrConfigurationFromPageId($rootPageId);
        $className = null;
        try {
            $result = $configuration->getObjectByPath('plugin.tx_solr.index.externalDataProvider.' . $tableName);
            if (isset($result[$tableName])) {
                $className = $result[$tableName];
            }
        } catch (\Exception $exception) {
        }

        if ($className !== null && !empty($className)) {
            if (!class_exists($className)) {
                return null;
            }
        }

        return GeneralUtility::makeInstance($className);
    }
}
