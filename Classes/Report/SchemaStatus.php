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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Reports\Status;
use TYPO3\CMS\Reports\StatusProviderInterface;

/**
 * Provides an status report about which schema version is used and checks
 * whether it fits the recommended version shipping with the extension.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class SchemaStatus implements StatusProviderInterface
{

    /**
     * The schema name property is constructed as follows:
     *
     * tx_solr  - The extension key
     * x-y-z    - The extension version this schema is meant to work with
     * YYYYMMDD - The date the schema file was changed the last time
     *
     * Must be updated when changing the schema.
     *
     * @var string
     */
    const RECOMMENDED_SCHEMA_VERSION = 'tx_solr-4-0-0--20160406';

    /**
     * Compiles a collection of schema version checks against each configured
     * Solr server. Only adds an entry if a schema other than the
     * recommended one was found.
     *
     */
    public function getStatus()
    {
        $reports = array();
        $solrConnections = GeneralUtility::makeInstance('ApacheSolrForTypo3\\Solr\\ConnectionManager')->getAllConnections();

        foreach ($solrConnections as $solrConnection) {
            if ($solrConnection->ping()
                && $solrConnection->getSchemaName() != self::RECOMMENDED_SCHEMA_VERSION
            ) {
                $message = '<p style="margin-bottom: 10px;">A schema different
					from the one provided with the extension was detected.</p>
					<p style="margin-bottom: 10px;">It is recommended to use the
					schema.xml file shipping with the Apache Solr for TYPO3
					extension as it provides an optimized field setup for
					using Solr with TYPO3. A difference can occur when you
					update the TYPO3 extension, but forget to update the
					schema.xml file on the Solr server. The schema sometimes
					changes to accommodate changes or new features in Apache
					Solr. Also make sure to restart the Tomcat server after
					updating the schema.xml file.</p>
					<p style="margin-bottom: 10px;">Your Solr server is
					currently using schema version <strong>'
                    . $solrConnection->getSchemaName() . '</strong>, the
					recommended schema version is <strong>'
                    . self::RECOMMENDED_SCHEMA_VERSION . '</strong>. You can
					find the recommended schema.xml file in the extension\'s
					resources folder: EXT:solr/Resources/Solr/. While
					you\'re at it, please make sure you\'re using the
					current solrconfig.xml file, too.</p>';

                $message .= '<p>Affected Solr server:</p>
					<ul>'
                    . '<li>Host: ' . $solrConnection->getHost() . '</li>'
                    . '<li>Port: ' . $solrConnection->getPort() . '</li>'
                    . '<li>Path: ' . $solrConnection->getPath() . '</li>
					</ul>';

                $status = GeneralUtility::makeInstance('TYPO3\\CMS\\Reports\\Status',
                    'Schema Version',
                    'Unsupported Schema',
                    $message,
                    Status::WARNING
                );

                $reports[] = $status;
            }
        }

        return $reports;
    }
}
