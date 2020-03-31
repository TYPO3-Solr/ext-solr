<?php
namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\Query\AbstractQueryBuilder;
use ApacheSolrForTypo3\Solr\System\Configuration\TypoScriptConfiguration;

/**
 * The FieldCollapsing ParameterProvider is responsible to build the solr query parameters
 * that are needed for the field collapsing.
 */
class FieldCollapsing extends AbstractDeactivatable implements ParameterBuilder
{
    /**
     * @var string
     */
    protected $collapseFieldName = 'variantId';

    /**
     * @var bool
     */
    protected $expand = false;

    /**
     * @var int
     */
    protected $expandRowCount = 10;

    /**
     * FieldCollapsing constructor.
     * @param bool $isEnabled
     * @param string $collapseFieldName
     * @param bool $expand
     * @param int $expandRowCount
     */
    public function __construct($isEnabled, $collapseFieldName = 'variantId', $expand = false, $expandRowCount = 10)
    {
        $this->isEnabled = $isEnabled;
        $this->collapseFieldName = $collapseFieldName;
        $this->expand = $expand;
        $this->expandRowCount = $expandRowCount;
    }

    /**
     * @return string
     */
    public function getCollapseFieldName(): string
    {
        return $this->collapseFieldName;
    }

    /**
     * @param string $collapseFieldName
     */
    public function setCollapseFieldName(string $collapseFieldName)
    {
        $this->collapseFieldName = $collapseFieldName;
    }

    /**
     * @return boolean
     */
    public function getIsExpand(): bool
    {
        return $this->expand;
    }

    /**
     * @param boolean $expand
     */
    public function setExpand(bool $expand)
    {
        $this->expand = $expand;
    }

    /**
     * @return int
     */
    public function getExpandRowCount(): int
    {
        return $this->expandRowCount;
    }

    /**
     * @param int $expandRowCount
     */
    public function setExpandRowCount(int $expandRowCount)
    {
        $this->expandRowCount = $expandRowCount;
    }

    /**
     * @param TypoScriptConfiguration $solrConfiguration
     * @return FieldCollapsing
     */
    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration)
    {
        $isEnabled = $solrConfiguration->getSearchVariants();
        if (!$isEnabled) {
            return new FieldCollapsing(false);
        }

        $collapseField = $solrConfiguration->getSearchVariantsField();
        $expand = (bool)$solrConfiguration->getSearchVariantsExpand();
        $expandRows = $solrConfiguration->getSearchVariantsLimit();

        return new FieldCollapsing(true, $collapseField, $expand, $expandRows);
    }

    /**
     * @return FieldCollapsing
     */
    public static function getEmpty()
    {
        return new FieldCollapsing(false);
    }

    /**
     * @param AbstractQueryBuilder $parentBuilder
     * @return AbstractQueryBuilder
     */
    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $query = $parentBuilder->getQuery();
        if(!$this->getIsEnabled()) {
            return $parentBuilder;
        }

        $parentBuilder->useFilter('{!collapse field=' . $this->getCollapseFieldName(). '}', 'fieldCollapsing');
        if($this->getIsExpand()) {
            $query->addParam('expand', 'true');
            $query->addParam('expand.rows', $this->getExpandRowCount());
        }

        return $parentBuilder;
    }
}
