<?php

namespace ApacheSolrForTypo3\Solr\Domain\Search\ResultSet;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015-2016 Timo Schmidt <timo.schmidt@dkd.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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

use ApacheSolrForTypo3\Solr\Query;
use ApacheSolrForTypo3\Solr\Search;
use IteratorAggregate;
use Traversable;

/**
 * Proxy class for \Apache_Solr_Document to customize \Apache_Solr_Document without
 * changing the library code.
 *
 * Implements
 *
 * @author Timo Schmidt <timo.schmidt@dkd.de>
 * @package TYPO3
 * @subpackage solr
 */
class SearchResult extends \Apache_Solr_Document
{

    /**
     * @var bool
     */
    protected $throwExceptions = false;


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
     * @var null
     */
    protected $variantParent = null;

    /**
     * @param \Apache_Solr_Document $document
     * @param bool $throwExceptions
     */
    public function __construct(\Apache_Solr_Document $document, $throwExceptions = false)
    {
        $this->throwExceptions = false;
        $this->_documentBoost = $document->_documentBoost;
        $this->_fields = $document->_fields;
        $this->_fieldBoosts = $document->_fieldBoosts;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return null
     * @throws \Exception
     * @throws \RuntimeException
     */
    public function __call($name, $arguments)
    {
        try {
            return parent::__call($name, $arguments);
        } catch (\RuntimeException $e) {
            if ($this->throwExceptions) {
                throw $e;
            }
        }
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
     * @return boolean
     */
    public function getIsVariant()
    {
        return $this->isVariant;
    }

    /**
     * @param boolean $isVariant
     */
    public function setIsVariant($isVariant)
    {
        $this->isVariant = $isVariant;
    }

    /**
     * @return null
     */
    public function getVariantParent()
    {
        return $this->variantParent;
    }

    /**
     * @param null $variantParent
     */
    public function setVariantParent($variantParent)
    {
        $this->variantParent = $variantParent;
    }
}
