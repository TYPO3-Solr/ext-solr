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

namespace ApacheSolrForTypo3\Solr\Tests\Unit\Controller\Backend\Search;

/**
 * Class IndexQueueTestUpdateHandler
 */
class IndexQueueTestUpdateHandler
{

    /**
     * @param string $type
     * @param int $uid
     * @param int $pageId
     * @param int $languageUid
     * @return int
     */
    public function postProcessIndexQueueUpdateItem(string $type, int $uid, int $pageId, int $languageUid): int
    {
        return 5;
    }
}
