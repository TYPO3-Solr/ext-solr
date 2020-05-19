<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Paginate;

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

use ApacheSolrForTypo3\Solr\Domain\Search\ResultSet\Grouping\GroupItem;
use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\AbstractUriViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class GroupItemPageViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class GroupItemPageViewHelper extends AbstractUriViewHelper
{

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('page', 'int', 'The page', false, 0);
        $this->registerArgument('groupItem', GroupItem::class, 'The group item', true);

    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $page = $arguments['page'];
        $groupItem = $arguments['groupItem'];
        $previousRequest = static::getUsedSearchRequestFromRenderingContext($renderingContext);
        $uri = self::getSearchUriBuilder($renderingContext)->getResultGroupItemPageUri($previousRequest, $groupItem, (int)$page);
        return $uri;
    }
}
