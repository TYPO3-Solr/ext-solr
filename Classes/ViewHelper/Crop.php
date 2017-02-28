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
 * Crop viewhelper to to shorten strings
 * Replaces viewhelpers ###CROP:string|length|cropIndicator|cropFullWords###
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
class Crop implements ViewHelper
{

    // defaults if neither is given trough the view helper marker, nor through TS
    protected $maxLength = 30;
    protected $cropIndicator = '...';
    protected $cropFullWords = true;

    /**
     * Constructor
     * @param array $arguments
     */
    public function __construct(array $arguments = [])
    {
        $configuration = Util::getSolrConfiguration();
        $cropConfiguration = $configuration->getViewHelpersCropConfiguration();

        if (!empty($cropConfiguration['maxLength'])) {
            $this->maxLength = $cropConfiguration['maxLength'];
        }

        if (!empty($cropConfiguration['cropIndicator'])) {
            $this->cropIndicator = $cropConfiguration['cropIndicator'];
        }

        if (isset($cropConfiguration['cropFullWords'])) {
            $this->cropFullWords = (boolean)$cropConfiguration ['cropFullWords'];
        }
    }

    /**
     * returns the given string shortened to a max length of optionally set chars.
     * If no maxLength and/or cropIndicator parameters are set, default values apply
     *
     * @param array $arguments
     * @return string
     */
    public function execute(array $arguments = [])
    {
        $stringToCrop = $arguments[0];

        $maxLength = $this->maxLength;
        if (isset($arguments[1])) {
            $maxLength = (int)$arguments[1];
        }

        $cropIndicator = $this->cropIndicator;
        if (isset($arguments[2])) {
            $cropIndicator = $arguments[2];
        }

        if (!empty($arguments[3])) {
            $this->cropFullWords = true;
        }

        $contentObject = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $contentObject->start([], '');
        $croppedString = $contentObject->cropHTML(
            $stringToCrop,
            $maxLength . '|' . $cropIndicator . ($this->cropFullWords ? '|1' : '')
        );

        return $croppedString;
    }
}
