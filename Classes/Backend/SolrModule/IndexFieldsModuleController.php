<?php
namespace ApacheSolrForTypo3\Solr\Backend\SolrModule;

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

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Index Fields Module
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class IndexFieldsModuleController extends AbstractModuleController
{

    /**
     * Module name, used to identify a module f.e. in URL parameters.
     *
     * @var string
     */
    protected $moduleName = 'IndexFields';

    /**
     * Module title, shows up in the module menu.
     *
     * @var string
     */
    protected $moduleTitle = 'Index Fields';

    /**
     * Gets Luke meta data for the currently selected core and provides a list
     * of that data.
     *
     * @return void
     */
    public function indexAction()
    {
        $solrConnection = $this->getSelectedCoreSolrConnection();

        if ($solrConnection->ping()) {
            $lukeData = $solrConnection->getLukeMetaData();

            $registry = GeneralUtility::makeInstance(Registry::class);
            $limit = $registry->get('tx_solr', 'luke.limit', 20000);
            $limitNote = '';

            if (isset($lukeData->index->numDocs) && $lukeData->index->numDocs > $limit) {
                $limitNote = '<em>Too many terms</em>';
            } elseif (isset($lukeData->index->numDocs)) {
                $limitNote = 'Nothing indexed';
                // below limit, so we can get more data
                // Note: we use 2 since 1 fails on Ubuntu Hardy.
                $lukeData = $solrConnection->getLukeMetaData(2);
            }

            $fields = $this->getFields($lukeData, $limitNote);
            $coreMetrics = $this->getCoreMetrics($lukeData, $fields);

            $this->view->assign('noError', 'OK');
            $this->view->assign('fields', $fields);
            $this->view->assign('coreMetrics', $coreMetrics);
        } else {
            $this->view->assign('noError', null);

            $this->addFlashMessage(
                '',
                'Unable to contact your Apache Solr server.',
                FlashMessage::ERROR
            );
        }
    }

    /**
     * Finds the Solr connection to use for the currently selected core.
     *
     * @return \ApacheSolrForTypo3\Solr\SolrService Solr connection
     */
    protected function getSelectedCoreSolrConnection()
    {
        $currentCoreConnection = null;

        $solrConnections = $this->connectionManager->getConnectionsBySite($this->site);
        $currentCore = $this->moduleDataStorageService->loadModuleData()->getCore();

        foreach ($solrConnections as $solrConnection) {
            if ($solrConnection->getPath() == $currentCore) {
                $currentCoreConnection = $solrConnection;
                break;
            }
        }

        if (is_null($currentCoreConnection)) {
            // when switching sites $currentCore is empty and nothing matched
            $currentCoreConnection = $solrConnections[0];
        }

        return $currentCoreConnection;
    }

    /**
     * Gets field metrics.
     *
     * @param \Apache_Solr_Response $lukeData Luke index data
     * @param string $limitNote Note to display if there are too many documents in the index to show number of terms for a field
     *
     * @return array An array of field metrics
     */
    protected function getFields(\Apache_Solr_Response $lukeData, $limitNote)
    {
        $rows = [];

        $fields = (array)$lukeData->fields;
        foreach ($fields as $name => $field) {
            $rows[$name] = [
                'name' => $name,
                'type' => $field->type,
                'docs' => isset($field->docs) ? $field->docs : 0,
                'terms' => isset($field->distinct) ? $field->distinct : $limitNote
            ];
        }
        ksort($rows);

        return $rows;
    }

    /**
     * Gets general core metrics.
     *
     * @param \Apache_Solr_Response $lukeData Luke index data
     * @param array $fields Fields metrics
     *
     * @return array An array of core metrics
     */
    protected function getCoreMetrics(\Apache_Solr_Response $lukeData, array $fields)
    {
        $coreMetrics = [
            'numberOfDocuments' => $lukeData->index->numDocs,
            'numberOfDeletedDocuments' => $lukeData->index->deletedDocs,
            'numberOfTerms' => $lukeData->index->numTerms,
            'numberOfFields' => count($fields)
        ];

        return $coreMetrics;
    }
}
