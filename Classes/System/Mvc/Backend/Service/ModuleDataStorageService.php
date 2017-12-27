<?php
namespace ApacheSolrForTypo3\Solr\System\Mvc\Backend\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
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

use ApacheSolrForTypo3\Solr\System\Mvc\Backend\ModuleData;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Module data storage service. Used to store and retrieve module state (eg.
 * checkboxes, selections).
 */
class ModuleDataStorageService implements SingletonInterface
{

    /**
     * @var string
     */
    const KEY = 'tx_solr';

    /**
     * Loads module data for user settings or returns a fresh object initially
     *
     * @return ModuleData
     */
    public function loadModuleData()
    {
        $moduleData = $GLOBALS['BE_USER']->getModuleData(self::KEY);

        $this->unsetModuleDataIfCanNotBeSerialized($moduleData);
        if (empty($moduleData) || !$moduleData) {
            $moduleData = GeneralUtility::makeInstance(ModuleData::class);
        } else {
            $moduleData = unserialize($moduleData);
        }

        return $moduleData;
    }

    /**
     * Persists serialized module data to user settings
     *
     * @param ModuleData $moduleData
     * @return void
     */
    public function persistModuleData(ModuleData $moduleData)
    {
        $GLOBALS['BE_USER']->pushModuleData(self::KEY, serialize($moduleData));
    }

    /**
     * Unsets not serializable module data.
     *
     * @param string|null $serializedModuleData
     */
    private function unsetModuleDataIfCanNotBeSerialized(string &$serializedModuleData = null)
    {
        if (!isset($serializedModuleData)) {
            $serializedModuleData = '';
            return;
        }
        if (false !== strpos($serializedModuleData, 'ApacheSolrForTypo3\\Solr\\Domain\\Model\\ModuleData')
            || false !== strpos($serializedModuleData, 'Tx_Solr_Site')) {
            $serializedModuleData = '';
        }
    }
}
