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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Facet\Area;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetCollection;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
use Closure;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Class GroupViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de> *
 */
class GroupViewHelper extends AbstractSolrFrontendViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @inheritdoc
     */
    protected $escapeOutput = false;

    /**
     * @inheritDoc
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('facets', FacetCollection::class, 'The facets that should be filtered', true);
        $this->registerArgument('groupName', 'string', 'The groupName that should be shown', false, 'main');
    }

    /**
     * Renders group
     */
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext,
    ) {
        /* @var FacetCollection $facets */
        $facets = $arguments['facets'];
        $requiredGroup = $arguments['groupName'] ?? 'main';
        $filtered = $facets->getByGroupName($requiredGroup);

        $templateVariableProvider = $renderingContext->getVariableProvider();
        $templateVariableProvider->add('areaFacets', $filtered);
        $content = $renderChildrenClosure();
        $templateVariableProvider->remove('areaFacets');

        return $content;
    }
}
