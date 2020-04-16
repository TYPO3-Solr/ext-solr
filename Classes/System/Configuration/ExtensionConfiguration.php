<?php
namespace ApacheSolrForTypo3\Solr\System\Configuration;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017- Timo Schmidt <timo.schmidt@dkd.de
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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class encapsulates the access to the extension configuration.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ExtensionConfiguration
{
    /**
     * Extension Configuration
     *
     * @var array
     */
    protected $configuration = [];

    /**
     * ExtensionConfiguration constructor.
     * @param array $configurationToUse
     */
    public function __construct($configurationToUse = [])
    {
        if (empty($configurationToUse)) {
            $this->configuration = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('solr');
        } else {
            $this->configuration = $configurationToUse;
        }
    }

    /**
     * Get configuration for useConfigurationFromClosestTemplate
     *
     * @return bool
     */
    public function getIsUseConfigurationFromClosestTemplateEnabled()
    {
        return (bool)$this->getConfigurationOrDefaultValue('useConfigurationFromClosestTemplate', false);
    }

    /**
     * Get configuration for useConfigurationTrackRecordsOutsideSiteroot
     *
     * @return bool
     */
    public function getIsUseConfigurationTrackRecordsOutsideSiteroot()
    {
        return (bool)$this->getConfigurationOrDefaultValue('useConfigurationTrackRecordsOutsideSiteroot', true);
    }

    /**
     * Get configuration for allowSelfSignedCertificates
     *
     * @return bool
     */
    public function getIsSelfSignedCertificatesEnabled()
    {
        return (bool)$this->getConfigurationOrDefaultValue('allowSelfSignedCertificates', false);
    }

    /**
     * Get configuration for useConfigurationMonitorTables
     *
     * @return array of tableName
     */
    public function getIsUseConfigurationMonitorTables()
    {
        $monitorTables = [];
        $monitorTablesList = $this->getConfigurationOrDefaultValue('useConfigurationMonitorTables', '');

        if (empty($monitorTablesList)) {
            return $monitorTables;
        }

        return GeneralUtility::trimExplode(',', $monitorTablesList);
    }

    /**
     * Get configuration for allowLegacySiteMode
     *
     * @return bool
     */
    public function getIsAllowLegacySiteModeEnabled(): bool
    {
        trigger_error('solr:deprecation: Method getIsAllowLegacySiteModeEnabled is deprecated since EXT:solr 11 and will be removed in 12. Since EXT:solr 10 legacy site handling is deprecated and was removed in EXT:solr 11.', E_USER_DEPRECATED);

        //@todo throw exception if set to true and log deprecation
        $legacyModeIsActive = $this->getConfigurationOrDefaultValue('allowLegacySiteMode', false);
        if($legacyModeIsActive === true) {
            throw new \InvalidArgumentException("Legacy mode is not supported anymore, please migrate your system to use sitehandling now!");
        }

        return false;
    }

    /**
     * @param string $key
     * @param mixed $defaultValue
     * @return mixed
     */
    protected function getConfigurationOrDefaultValue($key, $defaultValue)
    {
        return isset($this->configuration[$key]) ? $this->configuration[$key] : $defaultValue;
    }
}
