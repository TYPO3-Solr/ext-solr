<?php
namespace ApacheSolrForTypo3\Solr\Tests\Integration;


use ApacheSolrForTypo3\Solr\GarbageCollectorPostProcessor;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Class TestGarbageCollectorPostProcessor
 * @package ApacheSolrForTypo3\Solr\Tests\Integration
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
     * @see \ApacheSolrForTypo3\Solr\GarbageCollector->collectGarbage()
     */
    public function postProcessGarbageCollector($table, $uid)
    {
        $this->hookWasCalled = true;
    }

    /**
     * @return boolean
     */
    public function isHookWasCalled(): bool
    {
        return $this->hookWasCalled;
    }
}

