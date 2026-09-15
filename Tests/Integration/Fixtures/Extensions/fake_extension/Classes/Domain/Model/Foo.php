<?php

namespace ApacheSolrForTypo3\FakeExtension\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Foo extends AbstractEntity
{
    protected string $title;
    protected bool $hidden;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): void
    {
        $this->hidden = $hidden;
    }
}
