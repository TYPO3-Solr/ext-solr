<?php

declare(strict_types=1);

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
use Solarium\Component\Highlighting\HighlightingInterface as SolariumHighlightingInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The Highlighting ParameterProvider is responsible to build the solr query parameters
 * that are needed for the highlighting.
 */
class Highlighting extends AbstractDeactivatable implements ParameterBuilderInterface
{
    protected int $fragmentSize = 200;

    protected string $highlightingFieldList = '';

    protected string $prefix = '';

    protected string $postfix = '';

    /**
     * Highlighting constructor.
     */
    public function __construct(
        bool $isEnabled = false,
        int $fragmentSize = 200,
        string $highlightingFieldList = '',
        string $prefix = '',
        string $postfix = '',
    ) {
        $this->isEnabled = $isEnabled;
        $this->fragmentSize = $fragmentSize;
        $this->highlightingFieldList = $highlightingFieldList;
        $this->prefix = $prefix;
        $this->postfix = $postfix;
    }

    public function getFragmentSize(): int
    {
        return $this->fragmentSize;
    }

    public function setFragmentSize(int $fragmentSize): void
    {
        $this->fragmentSize = $fragmentSize;
    }

    public function getHighlightingFieldList(): string
    {
        return $this->highlightingFieldList;
    }

    public function setHighlightingFieldList(string $highlightingFieldList): void
    {
        $this->highlightingFieldList = $highlightingFieldList;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function getPostfix(): string
    {
        return $this->postfix;
    }

    public function setPostfix(string $postfix): void
    {
        $this->postfix = $postfix;
    }

    /**
     * @deprecated Highlighting::getUseFastVectorHighlighter() is deprecated and will be removed in v14.
     *             The Unified Highlighter is now used unconditionally; the return value has no effect on the built query.
     */
    public function getUseFastVectorHighlighter(): bool
    {
        trigger_error(
            'Highlighting::getUseFastVectorHighlighter() is deprecated and will be removed in v14. '
            . 'The Unified Highlighter is now used unconditionally; the return value has no effect on the built query.',
            E_USER_DEPRECATED,
        );
        return $this->fragmentSize >= 18;
    }

    public static function fromTypoScriptConfiguration(TypoScriptConfiguration $solrConfiguration): Highlighting
    {
        $isEnabled = $solrConfiguration->getIsSearchResultsHighlightingEnabled();
        if (!$isEnabled) {
            return new Highlighting(false);
        }

        $fragmentSize = $solrConfiguration->getSearchResultsHighlightingFragmentSize();
        $highlightingFields = $solrConfiguration->getSearchResultsHighlightingFields();
        $wrap = explode('|', $solrConfiguration->getSearchResultsHighlightingWrap());
        $prefix = $wrap[0] ?? '';
        $postfix = $wrap[1] ?? '';

        return new Highlighting(true, $fragmentSize, $highlightingFields, $prefix, $postfix);
    }

    public static function getEmpty(): Highlighting
    {
        return new Highlighting(false);
    }

    public function build(AbstractQueryBuilder $parentBuilder): AbstractQueryBuilder
    {
        $query = $parentBuilder->getQuery();
        if (!$this->getIsEnabled()) {
            $query->removeComponent($query->getHighlighting());
            return $parentBuilder;
        }

        $highlighting = $query->getHighlighting();
        $highlighting->setFragSize($this->getFragmentSize());
        $highlighting->setFields(GeneralUtility::trimExplode(',', $this->getHighlightingFieldList()));
        $highlighting->setMethod(SolariumHighlightingInterface::METHOD_UNIFIED);
        $highlighting->setOffsetSource(SolariumHighlightingInterface::OFFSETSOURCE_ANALYSIS);
        $highlighting->setBoundaryScannerType('WORD');
        $highlighting->setFragsizeIsMinimum(false);

        if ($this->getPrefix() !== '' && $this->getPostfix() !== '') {
            $highlighting->setSimplePrefix($this->getPrefix());
            $highlighting->setSimplePostfix($this->getPostfix());
        }

        return $parentBuilder;
    }
}
