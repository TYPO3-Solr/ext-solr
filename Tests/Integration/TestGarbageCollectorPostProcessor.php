<?php

namespace ApacheSolrForTypo3\Solr\Tests\Integration;

use ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class TestGarbageCollectorPostProcessor
 */
class TestGarbageCollectorPostProcessor implements SingletonInterface, GarbageCollectorPostProcessor
{
    protected bool $hookWasCalled = false;

    /**
     * Post-processing of garbage collector
     *
     * @see \ApacheSolrForTypo3\Solr\GarbageCollector::collectGarbage()
     */
    public function postProcessGarbageCollector(string $table, int $uid)
    {
        $this->hookWasCalled = true;
    }

    public function isHookWasCalled(): bool
    {
        return $this->hookWasCalled;
    }
}
