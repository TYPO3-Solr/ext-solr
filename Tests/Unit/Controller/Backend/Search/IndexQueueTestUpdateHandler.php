<?php
namespace ApacheSolrForTypo3\Solr\Tests\Unit\Controller\Backend\Search;

/**
 * Class IndexQueueTestUpdateHandler
 * @package ApacheSolrForTypo3\Solr\Tests\Unit\Controller\Backend\Search
 */
class IndexQueueTestUpdateHandler {

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