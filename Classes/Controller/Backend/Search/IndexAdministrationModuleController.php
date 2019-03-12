<?php
namespace ApacheSolrForTypo3\Solr\Controller\Backend\Search;

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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\IndexQueue\Queue;
use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\ReferringRequest;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Index Administration Module
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class IndexAdministrationModuleController extends AbstractModuleController
{

    /**
     * @var Queue
     */
    protected $indexQueue;

    /**
     * @var ConnectionManager
     */
    protected $solrConnectionManager = null;

    /**
     * @param ConnectionManager $solrConnectionManager
     */
    public function setSolrConnectionManager(ConnectionManager $solrConnectionManager)
    {
        $this->solrConnectionManager = $solrConnectionManager;
    }

    /**
     * Initializes the controller before invoking an action method.
     */
    protected function initializeAction()
    {
        parent::initializeAction();
        $this->indexQueue = GeneralUtility::makeInstance(Queue::class);
        $this->solrConnectionManager = GeneralUtility::makeInstance(ConnectionManager::class);
    }

    /**
     * Index action, shows an overview of available index maintenance operations.
     *
     * @return void
     */
    public function indexAction()
    {
        if ($this->selectedSite === null || empty($this->solrConnectionManager->getConnectionsBySite($this->selectedSite))) {
            $this->view->assign('can_not_proceed', true);
        }
    }

    /**
     * Empties the site's indexes.
     *
     * @return void
     */
    public function emptyIndexAction()
    {
        $siteHash = $this->selectedSite->getSiteHash();

        try {
            $affectedCores = [];
            $solrServers = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);
            foreach ($solrServers as $solrServer) {
                $writeService = $solrServer->getWriteService();
                /* @var $solrServer SolrConnection */
                $writeService->deleteByQuery('siteHash:' . $siteHash);
                $writeService->commit(false, false, false);
                $affectedCores[] = $writeService->getPrimaryEndpoint()->getCore();
            }
            $message = LocalizationUtility::translate('solr.backend.index_administration.index_emptied_all', 'Solr', [$this->selectedSite->getLabel(), implode(', ', $affectedCores)]);
            $this->addFlashMessage($message);
        } catch (\Exception $e) {
            $this->addFlashMessage(LocalizationUtility::translate('solr.backend.index_administration.error.on_empty_index', 'Solr', [$e->__toString()]), '', FlashMessage::ERROR);
        }

        $this->redirect('index');
    }

    /**
     * Empties the Index Queue
     *
     * @return void
     */
    public function clearIndexQueueAction()
    {
        $this->indexQueue->deleteItemsBySite($this->selectedSite);
        $this->addFlashMessage(
            LocalizationUtility::translate('solr.backend.index_administration.success.queue_emptied', 'Solr',
                [$this->selectedSite->getLabel()])
        );
        $this->redirectToReferrerModule();
    }

    /**
     * Reloads the site's Solr cores.
     *
     * @return void
     */
    public function reloadIndexConfigurationAction()
    {
        $coresReloaded = true;
        $reloadedCores = [];
        $solrServers = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);

        foreach ($solrServers as $solrServer) {
            /* @var $solrServer SolrConnection */
            $coreAdmin = $solrServer->getAdminService();
            $coreReloaded = $coreAdmin->reloadCore()->getHttpStatus() === 200;

            $coreName = $coreAdmin->getPrimaryEndpoint()->getCore();
            if (!$coreReloaded) {
                $coresReloaded = false;

                $this->addFlashMessage(
                    'Failed to reload index configuration for core "' . $coreName . '"',
                    '',
                    FlashMessage::ERROR
                );
                break;
            }

            $reloadedCores[] = $coreName;
        }

        if ($coresReloaded) {
            $this->addFlashMessage(
                'Core configuration reloaded (' . implode(', ', $reloadedCores) . ').',
                '',
                FlashMessage::OK
            );
        }

        $this->redirect('index');
    }

    /**
     * Redirects to the referrer module index Action.
     *
     * Fluids <f:form VH can not make urls to other modules properly.
     * The module name/key is not provided in the hidden fields __referrer by bulding form.
     * So this is currently the single way to make it possible.
     *
     * @todo: remove this method if f:form works properly between backend modules.
     */
    protected function redirectToReferrerModule()
    {
        $wasFromQueue = $this->request->hasArgument('fromQueue');
        if (!$wasFromQueue) {
            $this->redirect('index');
            return;
        }

        /* @var BackendUriBuilder $backendUriBuilder */
        $backendUriBuilder = GeneralUtility::makeInstance(BackendUriBuilder::class);

        $parameters =  ['id' => $this->selectedPageUID];
        $referringUri = $backendUriBuilder->buildUriFromRoute('searchbackend_SolrIndexqueue', $parameters);

        $this->redirectToUri($referringUri);
    }
}
