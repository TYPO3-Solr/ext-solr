<?php
namespace ApacheSolrForTypo3\Solr\Report;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\SolrService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;

/**
 * Provides a status report about whether the Access Filter Query Parser Plugin
 * is installed on the Solr server.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class AccessFilterPluginInstalledStatus implements StatusProviderInterface
{

    /**
     * Solr Access Filter plugin version.
     *
     * Must be updated when changing the plugin.
     *
     * @var string
     */
    const RECOMMENDED_PLUGIN_VERSION = '1.2.0';

    /**
     * The plugin's Java class name.
     *
     * @var string
     */
    const PLUGIN_CLASS_NAME = 'org.typo3.solr.search.AccessFilterQParserPlugin';


    /**
     * Compiles a collection of solrconfig.xml checks against each configured
     * Solr server. Only adds an entry if the Access Filter Query Parser Plugin
     * is not configured.
     *
     */
    public function getStatus()
    {
        $reports = array();
        $solrConnections = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager')->getAllConnections();

        foreach ($solrConnections as $solrConnection) {
            if ($solrConnection->ping()) {
                $installationStatus = $this->checkPluginInstallationStatus($solrConnection);
                $versionStatus = $this->checkPluginVersion($solrConnection);

                if (!is_null($installationStatus)) {
                    $reports[] = $installationStatus;
                }

                if (!is_null($versionStatus)) {
                    $reports[] = $versionStatus;
                }
            }
        }

        return $reports;
    }

    /**
     * Checks whether the Solr plugin is installed.
     *
     * @param \ApacheSolrForTypo3\Solr\SolrService $solrConnection
     * @return null|\TYPO3\CMS\Reports\Status
     */
    protected function checkPluginInstallationStatus(SolrService $solrConnection
    ) {
        $status = null;

        if (!$this->isPluginInstalled($solrConnection)) {
            $message = '<p style="margin-bottom: 10px;">
			EXT:solr comes with a plugin for the Apache Solr server to
			ensure TYPO3 access restrictions are enforced for search
			results, thus allowing visitors to only see results they are
			allowed to see. The plugin is not installed on the following
			server. It is recommended to install the plugin if you are using
			frontend user group access restrictions for your site.</p>';

            $message .= '<p>Affected Solr server:</p>
				<ul>'
                . '<li>Host: ' . $solrConnection->getHost() . '</li>'
                . '<li>Port: ' . $solrConnection->getPort() . '</li>'
                . '<li>Path: ' . $solrConnection->getPath() . '</li>
				</ul>';

            $message .= $this->getPluginDownloadMessage();

            $status = GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status',
                'Access Filter Plugin',
                'Not Installed',
                $message,
                Status::WARNING
            );
        }

        return $status;
    }

    /**
     * Checks whether the Solr plugin version is up to date.
     *
     * @param \ApacheSolrForTypo3\Solr\SolrService $solrConnection
     * @return null|\TYPO3\CMS\Reports\Status
     */
    protected function checkPluginVersion(SolrService $solrConnection)
    {
        $status = null;

        if ($this->isPluginInstalled($solrConnection)
            && $this->isPluginOutdated($solrConnection)
        ) {
            $installedPluginVersion = $this->getInstalledPluginVersion($solrConnection);

            $message = '<p style="margin-bottom: 10px;">The Solr Access Filter
			Plugin you are using is outdated. You are using version <strong>' .
                $installedPluginVersion . '</strong>, the currently recommended
			version is <strong>' . self::RECOMMENDED_PLUGIN_VERSION .
                '</strong>.</p>';

            $message .= '<p>Affected Solr server:</p>
				<ul>'
                . '<li>Host: ' . $solrConnection->getHost() . '</li>'
                . '<li>Port: ' . $solrConnection->getPort() . '</li>'
                . '<li>Path: ' . $solrConnection->getPath() . '</li>
				</ul>';

            $message .= $this->getPluginDownloadMessage();

            $status = GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status',
                'Access Filter Plugin',
                'Outdated',
                $message,
                Status::WARNING
            );
        }

        return $status;
    }

    /**
     * Checks whether the Access Filter Query Parser Plugin is installed for
     * the given Solr server instance.
     *
     * @param SolrService $solrConnection Solr connection to check for the plugin.
     * @return boolean True if the plugin is installed, FALSE otherwise.
     */
    protected function isPluginInstalled(SolrService $solrConnection)
    {
        $accessFilterQueryParserPluginInstalled = false;

        $pluginsInformation = $solrConnection->getPluginsInformation();

        if (isset($pluginsInformation->plugins->OTHER->{self::PLUGIN_CLASS_NAME})) {
            $accessFilterQueryParserPluginInstalled = true;
        }

        return $accessFilterQueryParserPluginInstalled;
    }

    /**
     * Checks whether the installed plugin is current.
     *
     * @param SolrService $solrConnection Solr connection to check for the plugin.
     * @return boolean True if the plugin is outdated, FALSE if it meets the current version recommendation.
     */
    protected function isPluginOutdated(SolrService $solrConnection)
    {
        $pluginVersion = $this->getInstalledPluginVersion($solrConnection);

        $pluginVersionOutdated = version_compare(
            $pluginVersion,
            self::RECOMMENDED_PLUGIN_VERSION,
            '<'
        );

        return $pluginVersionOutdated;
    }

    /**
     * Gets the version of the installed plugin.
     *
     * @param SolrService $solrConnection Solr connection to check for the plugin.
     * @return string The installed plugin's version number.
     */
    public function getInstalledPluginVersion(SolrService $solrConnection)
    {
        $version = '0.0.0';

        $pluginsInformation = $solrConnection->getPluginsInformation();
        $rawVersion = $pluginsInformation->plugins->OTHER->{self::PLUGIN_CLASS_NAME}->version;

        $explodedRawVersion = explode('-', $rawVersion);
        $version = $explodedRawVersion[0];

        return $version;
    }

    /**
     * Generates a paragraph with message containing a download link for the
     * current Solr TYPO3 plugin.
     */
    protected function getPluginDownloadMessage()
    {
        $pluginDownloadUrl = 'http://www.typo3-solr.com/fileadmin/files/solr/solr-typo3-plugin-' . self::RECOMMENDED_PLUGIN_VERSION . '.jar';

        $pluginDownloadMessage = '<p>Please download and install the <a href="'
            . $pluginDownloadUrl
            . '">current version of the plugin</a>.</p>';

        return $pluginDownloadMessage;
    }
}
