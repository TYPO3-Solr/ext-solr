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

namespace ApacheSolrForTypo3\Solr\IndexQueue;

/**
 * Allows to modify the data url before call the frontend form the index queue
 *
 * @author Markus Goldbach <markus.goldbach@dkd.de>
 */
interface PageIndexerDataUrlModifier
{

    /**
     * Modifies the given data url
     *
     * @param string $pageUrl the current data url.
     * @param array $urlData An array of url data
     * @return string the final data url
     */
    public function modifyDataUrl($pageUrl, array $urlData);
}
