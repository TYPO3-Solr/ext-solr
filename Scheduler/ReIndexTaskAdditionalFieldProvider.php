<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011-2015 Christoph Moeller <support@network-publishing.de>
*  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
*
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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;


/**
 * Adds an additional field to specify the Solr server to initialize the index queue for
 *
 * @author Christoph Moeller <support@network-publishing.de>
 * @package TYPO3
 * @subpackage solr
 */
class Tx_Solr_Scheduler_ReIndexTaskAdditionalFieldProvider implements AdditionalFieldProviderInterface {

	/**
	 * Task information
	 *
	 * @var array
	 */
	protected $taskInformation;

	/**
	 * Scheduler task
	 *
	 * @var AbstractTask|Tx_Solr_Scheduler_ReIndexTask|NULL
	 */
	protected $task = NULL;

	/**
	 * Scheduler Module
	 *
	 * @var SchedulerModuleController
	 */
	protected $schedulerModule;

	/**
	 * Selected site
	 *
	 * @var Tx_Solr_Site
	 */
	protected $site = NULL;


	protected function initialize(array $taskInfo, AbstractTask $task = NULL, SchedulerModuleController $schedulerModule) {
		$this->taskInformation = $taskInfo;
		$this->task            = $task;
		$this->schedulerModule = $schedulerModule;

		if ($schedulerModule->CMD == 'edit') {
			$this->site = $task->getSite();
		}
	}

	/**
	 * Used to define fields to provide the Solr server address when adding
	 * or editing a task.
	 *
	 * @param array $taskInfo: reference to the array containing the info used in the add/edit form
	 * @param AbstractTask $task: when editing, reference to the current task object. Null when adding.
	 * @param SchedulerModuleController $schedulerModule: reference to the calling object (Scheduler's BE module)
	 * @return array Array containing all the information pertaining to the additional fields
	 *									The array is multidimensional, keyed to the task class name and each field's id
	 *									For each field it provides an associative sub-array with the following:
	 */
	public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule) {
		$this->initialize($taskInfo, $task, $schedulerModule);

		$additionalFields = array();

		$additionalFields['site'] = array(
			'code'     => Tx_Solr_Site::getAvailableSitesSelector('tx_scheduler[site]', $this->site),
			'label'    => 'LLL:EXT:solr/Resources/Private/Language/ModuleScheduler.xml:field_site',
			'cshKey'   => '',
			'cshLabel' => ''
		);

		$additionalFields['indexingConfigurations'] = array(
			'code'     => $this->getIndexingConfigurationSelector(),
			'label'    => 'Index Queue configurations to re-index',
			'cshKey'   => '',
			'cshLabel' => ''
		);

		return $additionalFields;
	}

	protected function getIndexingConfigurationSelector() {
		$selectorMarkup = 'Please select a site first.';

		$this->schedulerModule->doc->getPageRenderer()->addCssFile('../typo3conf/ext/solr/Resources/Css/Backend/indexingconfigurationselectorfield.css');

		if (!is_null($this->site)) {
			$selectorField = GeneralUtility::makeInstance(
				'Tx_Solr_Backend_IndexingConfigurationSelectorField',
				$this->site
			);
			$selectorField->setFormElementName('tx_scheduler[indexingConfigurations]');
			$selectorField->setSelectedValues($this->task->getIndexingConfigurationsToReIndex());

			$selectorMarkup = $selectorField->render();
		}

		return $selectorMarkup;
	}

	/**
	 * Checks any additional data that is relevant to this task. If the task
	 * class is not relevant, the method is expected to return TRUE
	 *
	 * @param array $submittedData: reference to the array containing the data submitted by the user
	 * @param SchedulerModuleController $schedulerModule: reference to the calling object (Scheduler's BE module)
	 * @return boolean True if validation was ok (or selected class is not relevant), FALSE otherwise
	 */
	public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule) {
		$result = FALSE;

			// validate site
		$sites = Tx_Solr_Site::getAvailableSites();
		if (array_key_exists($submittedData['site'], $sites)) {
			$result = TRUE;
		}

		return $result;
	}

	/**
	 * Saves any additional input into the current task object if the task
	 * class matches.
	 *
	 * @param array $submittedData: array containing the data submitted by the user
	 * @param AbstractTask $task: reference to the current task object
	 */
	public function saveAdditionalFields(array $submittedData, AbstractTask $task) {
		$task->setSite(GeneralUtility::makeInstance('Tx_Solr_Site', $submittedData['site']));

		$indexingConfigurations = array();
		if (!empty($submittedData['indexingConfigurations'])) {
			$indexingConfigurations = $submittedData['indexingConfigurations'];
		}
		$task->setIndexingConfigurationsToReIndex($indexingConfigurations);
	}
}

