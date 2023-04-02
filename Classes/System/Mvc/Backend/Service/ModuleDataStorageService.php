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

namespace ApacheSolrForTypo3\Solr\System\Mvc\Backend\Service;

use ApacheSolrForTypo3\Solr\System\Mvc\Backend\ModuleData;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Module data storage service. Used to store and retrieve module state (e.g. checkboxes, selections).
 */
class ModuleDataStorageService implements SingletonInterface
{
    public const KEY = 'tx_solr';

    /**
     * Loads module data for user settings or returns a fresh object initially
     */
    public function loadModuleData(): ModuleData
    {
        $moduleData = $GLOBALS['BE_USER']->getModuleData(self::KEY);

        $this->unsetModuleDataIfCanNotBeSerialized($moduleData);
        if (empty($moduleData)) {
            $moduleData = GeneralUtility::makeInstance(ModuleData::class);
        } else {
            $moduleData = unserialize($moduleData);
        }

        return $moduleData;
    }

    /**
     * Persists serialized module data to user settings
     */
    public function persistModuleData(ModuleData $moduleData): void
    {
        $GLOBALS['BE_USER']->pushModuleData(self::KEY, serialize($moduleData));
    }

    /**
     * Unsets not serializable module data.
     */
    private function unsetModuleDataIfCanNotBeSerialized(string &$serializedModuleData = null): void
    {
        if (!isset($serializedModuleData)) {
            $serializedModuleData = '';
            return;
        }
        if (str_contains($serializedModuleData, 'ApacheSolrForTypo3\\Solr\\Domain\\Model\\ModuleData')
            || str_contains($serializedModuleData, 'Tx_Solr_Site')) {
            $serializedModuleData = '';
        }
    }
}
