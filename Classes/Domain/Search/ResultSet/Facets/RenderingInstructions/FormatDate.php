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

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\RenderingInstructions;

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
    protected $formatService;

    /**
     * FormatDate constructor.
     * @param FormatService|null $formatService
     */
    public function __construct(FormatService $formatService = null)
    {
        $this->formatService = $formatService ?? GeneralUtility::makeInstance(FormatService::class);
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
        $inputFormat = $conf['inputFormat'] ?? 'Y-m-d\TH:i:s\Z';
        $outputFormat = $conf['outputFormat'] ?? '';
        return $this->formatService->format($content, $inputFormat, $outputFormat);
    }
}
