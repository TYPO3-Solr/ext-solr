<?php
namespace ApacheSolrForTypo3\Solr\ViewHelper;

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

use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * viewhelper class to format unix timestamps as date
 * Replaces viewhelpers ###DATE:timestamp###
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package TYPO3
 * @subpackage solr
 */
class Date implements ViewHelper
{

    protected $dateFormat = null;

    /**
     * instance of ContentObjectRenderer
     *
     * @var ContentObjectRenderer
     */
    protected $contentObject = null;

    /**
     * Constructor
     *
     * @param array $arguments
     */
    public function __construct(array $arguments = array())
    {
        if (is_null($this->dateFormat) || is_null($this->contentObject)) {
            $configuration = Util::getSolrConfiguration();
            $this->dateFormat = $configuration->getValueByPathOrDefaultValue('plugin.tx_solr.general.dateFormat.', array('date' => 'd.m.Y H:i'));
            $this->contentObject = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\ContentObject\\ContentObjectRenderer');
        }
    }

    /**
     * Converts a given unix timestamp to a human readable date
     *
     * @param array $arguments
     * @return string
     */
    public function execute(array $arguments = array())
    {
        $content = '';

        if (count($arguments) > 1) {
            $this->dateFormat['date'] = $arguments[1];
        }
        if (is_numeric($arguments[0])) {
            $content = $this->contentObject->stdWrap($arguments[0],
                $this->dateFormat);
        }

        return $content;
    }
}
