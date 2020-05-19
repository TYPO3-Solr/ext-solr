<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Sorting;

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

use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\AbstractUriViewHelper;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class SetSortingViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class SetSortingViewHelper extends AbstractUriViewHelper
{

    /**
     * Initializes the arguments
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('sortingName', 'string', 'The sortingName', true);
        $this->registerArgument('sortingDirection', 'string', 'The sortingDirection', true);
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $sortingName = $arguments['sortingName'];
        $sortingDirection = $arguments['sortingDirection'];
        $previousRequest = static::getUsedSearchRequestFromRenderingContext($renderingContext);
        $uri = self::getSearchUriBuilder($renderingContext)->getSetSortingUri($previousRequest, $sortingName, $sortingDirection);
        return $uri;
    }
}
