<?php
namespace ApacheSolrForTypo3\Solr\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\ConnectionManager;
use ApacheSolrForTypo3\Solr\PingFailedException;
use ApacheSolrForTypo3\Solr\SolrService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;


/**
 * Provides an status report about whether a connection to the Solr server can
 * be established.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class SolrStatus implements StatusProviderInterface
{

    /**
     * Connection Manager
     *
     * @var ConnectionManager
     */
    protected $connectionManager = null;

    /**
     * Holds the response status
     *
     * @var integer
     */
    protected $responseStatus = Status::OK;

    /**
     * Holds the response message build by the checks
     * 
     * @var string
     */
    protected $responseMessage = '';


    /**
     * @return string
     */
    protected function getMessageTemplate()
    {
        return <<<'TEMPLATE'
<ul>
   <li style="padding-bottom: 10px;">Site: ###SITE###</li>
   <li>Scheme: ###SCHEME###</li>
   <l>Host: ###HOST###</li>
   <li>Port: ###PORT###</li>
   <li style="padding-bottom: 10px;">Path: ###PATH###</li>
   <li>Apache Solr: ###VERSION###</li>
   <li>Ping Query Time: ###PING###</li>
   <li>schema.xml: ###SOLR_SCHEMA###</li>
   <li>solrconfig.xml: ###SOLR_CONFIG###</li>
   <li>Access Filter Plugin: ###ACCESS_FILTER_PLUGIN###</li>
</ul>
TEMPLATE;
    }

    /**
     * Replaces a value in the template.
     *
     * @param string $response
     * @param string $marker
     * @param string $value
     * @return string
     */
    protected function replaceMarkerInResponse($response, $marker, $value)
    {
        return str_replace('###'.$marker.'###', $value, $response);
    }

    /**
     * Compiles a collection of status checks against each configured Solr server.
     *
     */
    public function getStatus()
    {
        $reports = array();
        $this->connectionManager = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager');

        $solrConnections = $this->connectionManager->getAllConfigurations();

        foreach ($solrConnections as $solrConnection) {
            $reports[] = $this->getConnectionStatus($solrConnection);
        }

        return $reports;
    }

    /**
     * Checks whether a Solr server is available and provides some information.
     *
     * @param array $solrConnection Solr connection parameters
     * @return Status Status of the Solr connection
     */
    protected function getConnectionStatus(array $solrConnection)
    {
        $value = 'Your site has contacted the Apache Solr server.';
        $this->responseStatus = Status::OK;

        $solr = $this->connectionManager->getConnection(
            $solrConnection['solrHost'],
            $solrConnection['solrPort'],
            $solrConnection['solrPath'],
            $solrConnection['solrScheme']
        );

        $this->responseMessage = $this->getMessageTemplate();
        $this->responseMessage = $this->replaceMarkerInResponse($this->responseMessage, 'SITE', $solrConnection['label']);
        $this->responseMessage = $this->replaceMarkerInResponse($this->responseMessage, 'SCHEME', $solr->getScheme() );
        $this->responseMessage = $this->replaceMarkerInResponse($this->responseMessage, 'HOST', $solr->getHost() );
        $this->responseMessage = $this->replaceMarkerInResponse($this->responseMessage, 'PATH', $solr->getPath());
        $this->responseMessage = $this->replaceMarkerInResponse($this->responseMessage, 'PORT', $solr->getPort());
        $this->checkSolrVersion($solr);
        $this->checkAccessFilter($solr);
        $this->checkPingTime($solr);
        $this->checkSolrConfigName($solr);
        $this->checkSolrSchemaName($solr);

        if($this->responseStatus !== Status::OK) {
            $value = 'Failed contacting the Solr server.';
        }

        return GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status',
            'Apache Solr',
            $value,
            $this->responseMessage,
            $this->responseStatus
        );
    }

    /**
     * Checks the solr version and adds it to the report.
     *
     * @param SolrService $solr
     */
    protected function checkSolrVersion(SolrService $solr)
    {
        try {
            $solrVersion = $this->formatSolrVersion($solr->getSolrServerVersion());
        } catch (\Exception $e) {
            $this->responseStatus = Status::ERROR;
            $solrVersion = 'Error getting solr version: ' . $e->getMessage();
        }

        $this->responseMessage = $this->replaceMarkerInResponse($this->responseMessage, 'VERSION', $solrVersion);
    }

    /**
     * Checks the access filter setup and adds it to the report.
     *
     * @param SolrService $solr
     */
    protected function checkAccessFilter(SolrService $solr)
    {
        try {
            $accessFilterPluginStatus = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\Report\\AccessFilterPluginInstalledStatus');
            $accessFilterPluginVersion = $accessFilterPluginStatus->getInstalledPluginVersion($solr);
            $accessFilterMessage = $accessFilterPluginVersion;
        } catch (\Exception $e) {
            $this->responseStatus = Status::ERROR;
            $accessFilterMessage = 'Error getting access filter: ' . $e->getMessage();
        }
        $this->responseMessage = $this->replaceMarkerInResponse($this->responseMessage, 'ACCESS_FILTER_PLUGIN', $accessFilterMessage);

    }

    /**
     * Checks the ping time and adds it to the report.
     *
     * @param SolrService $solr
     */
    protected function checkPingTime(SolrService $solr)
    {
        try {
            $pingQueryTime = $solr->getPingRoundTripRuntime();
            $pingMessage = (int) $pingQueryTime . ' ms';
        } catch (PingFailedException $e) {
            $this->responseStatus = Status::ERROR;
            $pingMessage = 'Ping error: ' . $e->getMessage();
        }
        $this->responseMessage = $this->replaceMarkerInResponse($this->responseMessage, 'PING', $pingMessage);
    }

    /**
     * Checks the solr config name and adds it to the report.
     *
     * @param SolrService $solr
     */
    protected function checkSolrConfigName(SolrService $solr)
    {
        try {
            $solrConfigMessage = $solr->getSolrconfigName();
        } catch(\Exception $e) {
            $this->responseStatus =  Status::ERROR;
            $solrConfigMessage = 'Error determining solr config: '. $e->getMessage();
        }

        $this->responseMessage = $this->replaceMarkerInResponse($this->responseMessage, 'SOLR_CONFIG', $solrConfigMessage);
    }

    /**
     * Checks the solr schema name and adds it to the report.
     *
     * @param SolrService $solr
     */
    protected function checkSolrSchemaName(SolrService $solr)
    {
        try {
            $solrSchemaMessage = $solr->getSchemaName();
        } catch(\Exception $e) {
            $this->responseStatus  = Status::ERROR;
            $solrSchemaMessage = 'Error determining schema name: '. $e->getMessage();
        }

        $this->responseMessage = $this->replaceMarkerInResponse($this->responseMessage, 'SOLR_SCHEMA', $solrSchemaMessage);
    }

    /**
     * Formats the Apache Solr server version number. By default this is going
     * to be the simple major.minor.patch-level version. Custom Builds provide
     * more information though, in case of custom builds, their complete
     * version will be added, too.
     *
     * @param string $solrVersion Unformatted Apache Solr version number as provided by Solr.
     * @return string formatted short version number, in case of custom builds followed by the complete version number
     */
    protected function formatSolrVersion($solrVersion)
    {
        $explodedSolrVersion = explode('.', $solrVersion);

        $shortSolrVersion = $explodedSolrVersion[0]
            . '.' . $explodedSolrVersion[1]
            . '.' . $explodedSolrVersion[2];

        $formattedSolrVersion = $shortSolrVersion;

        if ($solrVersion != $shortSolrVersion) {
            $formattedSolrVersion .= ' (' . $solrVersion . ')';
        }

        return $formattedSolrVersion;
    }
}

