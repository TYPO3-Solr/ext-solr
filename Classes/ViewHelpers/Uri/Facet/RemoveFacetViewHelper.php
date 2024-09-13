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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Facet;

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\AbstractUriViewHelper;

/**
 * Class RemoveFacetViewHelper
 */
class RemoveFacetViewHelper extends AbstractUriViewHelper
{
    /**
     * @inheritdoc
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('facet', AbstractFacet::class, 'The facet', true);
    }

    /**
     * Renders URI for removing the facet.
     */
    public function render()
    {
        /** @var AbstractFacet $facet */
        $facet = $this->arguments['facet'];
        $previousRequest = $facet->getResultSet()->getUsedSearchRequest();

        return self::getSearchUriBuilder($this->renderingContext)->getRemoveFacetUri($previousRequest, $facet->getName());
    }
}
