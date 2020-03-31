<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Facet\Options\Group\Prefix;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\OptionBased\OptionCollection;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
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
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        /** @var  $options OptionCollection */
        $options = $arguments['options'];
        $length = isset($arguments['length']) ? $arguments['length'] : 1;
        $sortBy = isset($arguments['sortBy']) ? $arguments['sortBy'] : 'count';
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
    protected static function applySortBy(array $prefixes, $sortBy): array
    {
        if($sortBy === 'count' || $sortBy === '')
        {
            return $prefixes;
        }

        if($sortBy === 'alpha')
        {
            sort($prefixes);
            return $prefixes;
        }
    }
}
