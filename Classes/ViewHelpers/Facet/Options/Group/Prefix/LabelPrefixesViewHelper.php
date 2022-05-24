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
use Closure;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Class LabelPrefixesViewHelper
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class LabelPrefixesViewHelper extends AbstractSolrFrontendViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('options', OptionCollection::class, 'The options where prefixed should be available', true);
        $this->registerArgument('length', 'int', 'The length of the prefixed that should be retrieved', false, 1);
        $this->registerArgument('sortBy', 'string', 'The sorting mode (count,alpha)', false, 'count');
    }

    /**
     * @param array $arguments
     * @param Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     * @noinspection PhpMissingReturnTypeInspection
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        /** @var  $options OptionCollection */
        $options = $arguments['options'];
        $length = $arguments['length'] ?? 1;
        $sortBy = $arguments['sortBy'] ?? 'count';
        $prefixes = $options->getLowercaseLabelPrefixes($length);

        $prefixes = static::applySortBy($prefixes, $sortBy);

        $templateVariableProvider = $renderingContext->getVariableProvider();
        $templateVariableProvider->add('prefixes', $prefixes);
        $content = $renderChildrenClosure();
        $templateVariableProvider->remove('prefixes');

        return $content;
    }

    /**
     * Applies the configured sortBy.
     *
     * @param array $prefixes
     * @param string $sortBy
     * @return array
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
