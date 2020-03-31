<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Facet\Area;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\FacetCollection;
use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrFrontendViewHelper;
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
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('facets', FacetCollection::class, 'The facets that should be filtered', true);
        $this->registerArgument('groupName', 'string', 'The groupName that should be shown', false, 'main');
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        /** @var  $facets FacetCollection */
        $facets = $arguments['facets'];
        $requiredGroup = isset($arguments['groupName']) ? $arguments['groupName'] : 'main';
        $filtered = $facets->getByGroupName($requiredGroup);

        $templateVariableProvider = $renderingContext->getVariableProvider();
        $templateVariableProvider->add('areaFacets', $filtered);
        $content = $renderChildrenClosure();
        $templateVariableProvider->remove('areaFacets');

        return $content;
    }
}
