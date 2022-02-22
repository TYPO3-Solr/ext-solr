<?php
namespace ApacheSolrForTypo3\Solr\IndexQueue;

/**
 * DomainObject observer registry
 */
class DomainObjectObserverRegistry implements \TYPO3\CMS\Core\SingletonInterface
{

    /**
     * @var array
     */
    protected $domainObjectClassNames = [];

    /**
     *
     * @param string $domainObjectClassName
     */
    public function register(
        $domainObjectClassName
    ) {
        if (!is_subclass_of($domainObjectClassName, \TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject::class)) {
            throw new \LogicException($domainObjectClassName . ' must be a subclass of AbstractDomainObject', 1519394042);
        }

        $this->domainObjectClassNames[$domainObjectClassName] = $domainObjectClassName;
    }

    /**
     * @param string $domainObjectClassName
     * @return bool
     */
    public function isRegistered($domainObjectClassName)
    {
        return isset($this->domainObjectClassNames[$domainObjectClassName]);
    }

    /**
     *
     * @return array
     */
    public function getAll()
    {
        return $this->domainObjectClassNames;
    }
}
