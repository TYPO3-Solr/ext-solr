<?php

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

namespace ApacheSolrForTypo3\Solr\Controller\Backend\Search;

use ApacheSolrForTypo3\Solr\System\Solr\SolrConnection;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Index Administration Module
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class IndexAdministrationModuleController extends AbstractModuleController
{
    /**
     * Index action, shows an overview of available index maintenance operations.
     *
     * @return ResponseInterface
     */
    public function indexAction(): ResponseInterface
    {
        if ($this->selectedSite === null || empty($this->solrConnectionManager->getConnectionsBySite($this->selectedSite))) {
            $this->view->assign('can_not_proceed', true);
        }
        return $this->getModuleTemplateResponse();
    }

    /**
     * Empties the site's indexes.
     */
    public function emptyIndexAction(): ResponseInterface
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
        } catch (Throwable $e) {
            $this->addFlashMessage(LocalizationUtility::translate('solr.backend.index_administration.error.on_empty_index', 'Solr', [$e->__toString()]), '', FlashMessage::ERROR);
        }

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }

    /**
     * Reloads the site's Solr cores.
     *
     * @return ResponseInterface
     */
    public function reloadIndexConfigurationAction(): ResponseInterface
    {
        $coresReloaded = true;
        $reloadedCores = [];
        $solrServers = $this->solrConnectionManager->getConnectionsBySite($this->selectedSite);

        foreach ($solrServers as $solrServer) {
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

        return new RedirectResponse($this->uriBuilder->uriFor('index'), 303);
    }
}
