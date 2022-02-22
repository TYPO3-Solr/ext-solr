<?php
declare(strict_types=1);

namespace ApacheSolrForTypo3\Solr\Event;


use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;

/**
 *
 */
class BeforeDomainObjectObserverUpdate
{
    /**
     * @var DomainObjectInterface
     */
    protected $object;

    /**
     * @var int
     */
    protected $forcedChangeTime;

    /**
     * BeforeDomainObjectObserverUpdate constructor.
     *
     * @param DomainObjectInterface $object
     * @param int $forcedChangeTime
     */
    public function __construct(
        DomainObjectInterface $object,
        int $forcedChangeTime
    ) {
        $this->object = $object;
        $this->forcedChangeTime = $forcedChangeTime;
    }

    /**
     * @return DomainObjectInterface
     */
    public function getObject(): DomainObjectInterface
    {
        return $this->object;
    }

    /**
     * @param DomainObjectInterface $object
     */
    public function setObject(DomainObjectInterface $object): void
    {
        $this->object = $object;
    }

    /**
     * @return int
     */
    public function getForcedChangeTime(): int
    {
        return $this->forcedChangeTime;
    }

    /**
     * @param int $forcedChangeTime
     */
    public function setForcedChangeTime(int $forcedChangeTime): void
    {
        $this->forcedChangeTime = $forcedChangeTime;
    }
}
