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

use ApacheSolrForTypo3\Solr\Api;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Overview Module
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class OverviewModuleController extends AbstractModuleController
{


    /**
     * Module name, used to identify a module f.e. in URL parameters.
     *
     * @var string
     */
    protected $moduleName = 'Overview';

    /**
     * Module title, shows up in the module menu.
     *
     * @var string
     */
    protected $moduleTitle = 'Overview';

    protected $connections = array();

    /**
     * Index action, shows an overview of the state of the Solr index
     *
     * @return void
     */
    public function indexAction()
    {
        $this->checkConnections();

        $this->view->assign('site', $this->request->getArgument('site'));
        $this->view->assign('apiKey', Api::getApiKey());
    }

    /**
     * Checks whether the configured Solr server can be reached and provides a
     * flash message according to the result of the check.
     *
     * @return void
     */
    protected function checkConnections()
    {
        $connectedHosts = array();
        $missingHosts = array();

        foreach ($this->connections as $connection) {
            $coreUrl = $connection->getScheme() . '://' . $connection->getHost() . ':' . $connection->getPort() . $connection->getPath();

            if ($connection->ping()) {
                $connectedHosts[] = $coreUrl;
            } else {
                $missingHosts[] = $coreUrl;
            }
        }

        if (!empty($connectedHosts)) {
            $this->addFlashMessage(
                'Hosts contacted:' . PHP_EOL . implode(PHP_EOL, $connectedHosts),
                'Your Apache Solr server has been contacted.',
                FlashMessage::OK
            );
        }

        if (!empty($missingHosts)) {
            $this->addFlashMessage(
                'Hosts missing:<br />' . implode(PHP_EOL, $missingHosts),
                'Unable to contact your Apache Solr server.',
                FlashMessage::ERROR
            );
        }
    }

    /**
     * Initializes resources commonly needed for several actions
     *
     * @return void
     */
    protected function initializeAction()
    {
        parent::initializeAction();

        $connectionManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager');
        $this->connections = $connectionManager->getConnectionsBySite($this->site);
    }
}
