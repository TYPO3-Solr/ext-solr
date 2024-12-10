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
class FieldCollapsing extends AbstractDeactivatable implements ParameterBuilderInterface
{
    protected string $collapseFieldName = 'variantId';

    protected bool $expand = false;

    protected int $expandRowCount = 10;

    /**
     * FieldCollapsing constructor.
     */
    public function __construct(
        bool $isEnabled,
        string $collapseFieldName = 'variantId',
        bool $expand = false,
        int $expandRowCount = 10,
    ) {
        $this->isEnabled = $isEnabled;
        $this->collapseFieldName = $collapseFieldName;
        $this->expand = $expand;
        $this->expandRowCount = $expandRowCount;
    }

    public function getCollapseFieldName(): string
    {
        return $this->collapseFieldName;
    }

    public function setCollapseFieldName(string $collapseFieldName): void
    {
        $this->collapseFieldName = $collapseFieldName;
    }

    public function getIsExpand(): bool
    {
        return $this->expand;
    }

    public function setExpand(bool $expand): void
    {
        $this->expand = $expand;
    }

    public function getExpandRowCount(): int
    {
        return $this->expandRowCount;
    }

    public function setExpandRowCount(int $expandRowCount): void
    {
        $this->expandRowCount = $expandRowCount;
    }

    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration): FieldCollapsing
    {
        $isEnabled = $solrConfiguration->getSearchVariants();
        if (!$isEnabled) {
            return new FieldCollapsing(false);
        }

        // Deactivate collapsing/variants feature if grouping feature is enabled
        if ($solrConfiguration->getIsSearchGroupingEnabled()) {
            return new FieldCollapsing(false);
        }

        $collapseField = $solrConfiguration->getSearchVariantsField();
        $expand = $solrConfiguration->getSearchVariantsExpand();
        $expandRows = $solrConfiguration->getSearchVariantsLimit();

        return new FieldCollapsing(true, $collapseField, $expand, $expandRows);
    }

    public static function getEmpty(): FieldCollapsing
    {
        return new FieldCollapsing(false);
    }

    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $query = $parentBuilder->getQuery();
        if (!$this->getIsEnabled()) {
            return $parentBuilder;
        }

        $parentBuilder->useFilter('{!collapse field=' . $this->getCollapseFieldName() . '}', 'fieldCollapsing');
        if ($this->getIsExpand()) {
            $query->addParam('expand', 'true');
            $query->addParam('expand.rows', $this->getExpandRowCount());
        }

        return $parentBuilder;
    }
}
