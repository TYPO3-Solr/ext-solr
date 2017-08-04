<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Facet;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacet;
use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Facets\AbstractFacetItem;
use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\AbstractUriViewHelper;

/**
 * Class FacetAddOptionViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
abstract class AbstractValueViewHelper extends AbstractUriViewHelper
{
    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('facet', AbstractFacet::class, 'The facet', true);
        $this->registerArgument('facetItem', AbstractFacetItem::class, 'The facet item', false, null);
        $this->registerArgument('facetItemValue', 'string', 'The facet item', false, null);
    }

    /**
     * @param $arguments
     * @return string
     * @throws \InvalidArgumentException
     */
    protected static function getValueFromArguments($arguments)
    {
        if (isset($arguments['facetItem'])) {
            /** @var  $facetItem AbstractFacetItem */
            $facetItem = $arguments['facetItem'];
            $facetValue = $facetItem->getUriValue();
        } elseif (isset($arguments['facetItemValue'])) {
            $facetValue = $arguments['facetItemValue'];
        } else {
            throw new \InvalidArgumentException('No facetItem was passed, please pass either facetItem or facetItemValue');
        }

        return $facetValue;
    }
}
