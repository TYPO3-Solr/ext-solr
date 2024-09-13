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

/**
 * Class GroupViewHelper
 */
class GroupViewHelper extends AbstractSolrFrontendViewHelper
{
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
    public function render()
    {
        /** @var FacetCollection $facets */
        $facets = $this->arguments['facets'];
        $requiredGroup = $this->arguments['groupName'] ?? 'main';
        $filtered = $facets->getByGroupName($requiredGroup);

        $templateVariableProvider = $this->renderingContext->getVariableProvider();
        $templateVariableProvider->add('areaFacets', $filtered);
        $content = $this->renderChildren();
        $templateVariableProvider->remove('areaFacets');

        return $content;
    }
}
