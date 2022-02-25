<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class TestGarbageCollectorPostProcessor
 */
class TestGarbageCollectorPostProcessor implements SingletonInterface, GarbageCollectorPostProcessor
{

    /**
     * @var bool
     */
    protected $hookWasCalled = false;

    /**
     * Post processing of garbage collector
     *
     * @param string $table The record's table name.
     * @param int $uid The record's uid.
     * @see \ApacheSolrForTypo3\Solr\GarbageCollector::collectGarbage()
     */
    public function postProcessGarbageCollector(string $table, int $uid)
    {
        $this->hookWasCalled = true;
    }

    /**
     * @return bool
     */
    public function isHookWasCalled(): bool
    {
        return $this->hookWasCalled;
    }
}
