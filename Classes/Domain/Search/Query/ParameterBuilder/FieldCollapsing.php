<?php

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace ApacheSolrForTypo3\Solr\Domain\Search\Query\ParameterBuilder;

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
