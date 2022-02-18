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

namespace ApacheSolrForTypo3\Solr;

/**
 * Garbage Collector Post Processor interface
 *
 * @author Ingo Renner <ingo@typo3.org>
 */
interface GarbageCollectorPostProcessor
{

    /**
     * Post processing of garbage collector
     *
     * @param string $table The record's table name.
     * @param int $uid The record's uid.
     * @see \ApacheSolrForTypo3\Solr\GarbageCollector->collectGarbage()
     */
    public function postProcessGarbageCollector($table, $uid);
}
