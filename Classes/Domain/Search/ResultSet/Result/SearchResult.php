<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Result;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\System\Solr\Document\Document;

/**
 * Solr document class that should be used in the frontend in the search context.
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 */
class SearchResult extends Document
{
    /**
     * The variant field value
     *
     * Value of Solr collapse field, which is defined via
     * TypoScript variable "variants.variantField"
     *
     * @var string
     */
    protected $variantFieldValue = '';

    /**
     * Number of variants found
     *
     * May differ from documents in variants as
     * returned variants are limited by expand.rows
     *
     * @var int
     */
    protected $variantsNumFound = 0;

    /**
     * @var SearchResult[]
     */
    protected $variants = [];

    /**
     * Indicates if an instance of this document is a variant (a sub document of another).
     *
     * @var bool
     */
    protected $isVariant = false;

    /**
     * References the parent document of the document is a variant.
     *
     * @var SearchResult|null
     */
    protected $variantParent = null;

    /**
     * @var GroupItem
     */
    protected $groupItem = null;


    /**
     * @return GroupItem
     */
    public function getGroupItem(): GroupItem
    {
        return $this->groupItem;
    }

    /**
     * @return bool
     */
    public function getHasGroupItem()
    {
        return $this->groupItem !== null;
    }

    /**
     * @param GroupItem $group
     */
    public function setGroupItem(GroupItem $group)
    {
        $this->groupItem = $group;
    }

    /**
     * @return string
     */
    public function getVariantFieldValue(): string
    {
        return $this->variantFieldValue;
    }

    /**
     * @param string $variantFieldValue
     */
    public function setVariantFieldValue(string $variantFieldValue)
    {
        $this->variantFieldValue = $variantFieldValue;
    }

    /**
     * @return int
     */
    public function getVariantsNumFound(): int
    {
        return $this->variantsNumFound;
    }

    /**
     * @param int $numFound
     */
    public function setVariantsNumFound(int $numFound)
    {
        $this->variantsNumFound = $numFound;
    }

    /**
     * @return SearchResult[]
     */
    public function getVariants()
    {
        return $this->variants;
    }

    /**
     * @param SearchResult $expandedResult
     */
    public function addVariant(SearchResult $expandedResult)
    {
        $this->variants[] = $expandedResult;
    }

    /**
     * @return bool
     */
    public function getIsVariant()
    {
        return $this->isVariant;
    }

    /**
     * @param bool $isVariant
     */
    public function setIsVariant($isVariant)
    {
        $this->isVariant = $isVariant;
    }

    /**
     * @return SearchResult
     */
    public function getVariantParent()
    {
        return $this->variantParent;
    }

    /**
     * @param SearchResult $variantParent
     */
    public function setVariantParent(SearchResult $variantParent)
    {
        $this->variantParent = $variantParent;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->fields['content'];
    }

    /**
     * @return boolean
     */
    public function getIsElevated()
    {
        return $this->fields['isElevated'];
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->fields['type'];
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->fields['id'];
    }

    /**
     * @return float
     */
    public function getScore()
    {
        return $this->fields['score'];
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->fields['url'];
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->fields['title'];
    }
}
