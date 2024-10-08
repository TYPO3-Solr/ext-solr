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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Facet\Options\Group\Prefix;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\OptionCollection;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;

/**
 * Class LabelPrefixesViewHelper
 */
class LabelPrefixesViewHelper extends AbstractSolrFrontendViewHelper
{
    protected $escapeOutput = false;

    /**
     * Initializes the arguments
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('options', OptionCollection::class, 'The options where prefixed should be available', true);
        $this->registerArgument('length', 'int', 'The length of the prefixed that should be retrieved', false, 1);
        $this->registerArgument('sortBy', 'string', 'The sorting mode (count,alpha)', false, 'count');
    }

    /**
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function render()
    {
        /** @var OptionCollection $options */
        $options = $this->arguments['options'];
        $length = $this->arguments['length'] ?? 1;
        $sortBy = $this->arguments['sortBy'] ?? 'count';
        $prefixes = $options->getLowercaseLabelPrefixes($length);

        $prefixes = static::applySortBy($prefixes, $sortBy);

        $templateVariableProvider = $this->renderingContext->getVariableProvider();
        $templateVariableProvider->add('prefixes', $prefixes);
        $content = $this->renderChildren();
        $templateVariableProvider->remove('prefixes');

        return $content;
    }

    /**
     * Applies the configured sortBy.
     */
    protected static function applySortBy(array $prefixes, string $sortBy = ''): array
    {
        if ($sortBy === 'alpha') {
            sort($prefixes);
            return $prefixes;
        }
        // count is default
        return $prefixes;
    }
}
