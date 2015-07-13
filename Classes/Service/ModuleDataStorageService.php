<?php
namespace ApacheSolrForTypo3\Solr\Service;

/***************************************************************
*  Copyright notice
*
*  (c) 2013-2015 Ingo Renner <ingo@typo3.org>
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

use TYPO3\CMS\Core\SingletonInterface;
use ApacheSolrForTypo3\Solr\Domain\Model\ModuleData;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;

/**
 * Module data storage service. Used to store and retrieve module state (eg.
 * checkboxes, selections).
 *
 */
class ModuleDataStorageService implements SingletonInterface {

	/**
	 * @var string
	 */
	const KEY = 'tx_solr';

	/**
	 * @var ObjectManagerInterface
	 * @inject
	 */
	protected $objectManager;

	/**
	 * Loads module data for user settings or returns a fresh object initially
	 *
	 * @return \ApacheSolrForTypo3\Solr\Domain\Model\ModuleData
	 */
	public function loadModuleData() {
		$moduleData = $GLOBALS['BE_USER']->getModuleData(self::KEY);

		if (empty($moduleData) || !$moduleData) {
			$moduleData = $this->objectManager->get('ApacheSolrForTypo3\\Solr\\Domain\\Model\\ModuleData');
		} else {
			$moduleData = unserialize($moduleData);
		}

		return $moduleData;
	}

	/**
	 * Persists serialized module data to user settings
	 *
	 * @param \ApacheSolrForTypo3\Solr\Domain\Model\ModuleData $moduleData
	 * @return void
	 */
	public function persistModuleData(ModuleData $moduleData) {
		$GLOBALS['BE_USER']->pushModuleData(self::KEY, serialize($moduleData));
	}

}

