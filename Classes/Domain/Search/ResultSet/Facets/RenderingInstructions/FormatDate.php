<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RenderingInstructions;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Hendrik Putzek <hendrik.putzek@dkd.de>
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

use ApacheSolrForTypo3\Solr\System\DateTime\FormatService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Formats a given date string to another format
 *
 * Accepted typoscript parameters:
 * inputFormat -> The format of the input string
 * outputFormat -> The format of the processed string (output)
 *
 * If the input date can not be processed by the given inputFormat string it is
 * returned unprocessed.
 *
 * @author Hendrik Putzek <hendrik.putzek@dkd.de>
 */
class FormatDate
{

    /**
     * @var FormatService
     */
    protected $formatService = null;

    /**
     * FormatDate constructor.
     * @param FormatService|null $formatService
     */
    public function __construct(FormatService $formatService = null)
    {
        $this->formatService = is_null($formatService) ? GeneralUtility::makeInstance(FormatService::class) : $formatService;
    }

    /**
     * Formats a given date string to another format
     *
     * @param   string $content the content to process
     * @param   array $conf typoscript configuration
     * @return  string formatted  date
     */
    public function format($content, $conf)
    {
        // set default values
        $inputFormat = $conf['inputFormat'] ?: 'Y-m-d\TH:i:s\Z';
        $outputFormat = $conf['outputFormat'] ?: '';
        return $this->formatService->format($content, $inputFormat, $outputFormat);
    }
}
