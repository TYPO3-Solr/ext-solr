<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Sorting;

use ApacheSolrForTypo3\Solr\System\Data\AbstractCollection;

/**
 * Class SortingCollection
 */
class SortingCollection extends AbstractCollection
{
    protected ?Sorting $selected = null;

    public function addSorting(Sorting $sorting): void
    {
        if ($sorting->getSelected()) {
            $this->selected = $sorting;
        }

        $this->data[$sorting->getName()] = $sorting;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }
    public function getData(): array
    {
        return $this->data;
    }

    protected function setSelected(Sorting $selected): void
    {
        $this->selected = $selected;
    }

    public function getSelected(): ?Sorting
    {
        return $this->selected;
    }

    public function getHasSelected(): bool
    {
        return $this->selected !== null;
    }
}
