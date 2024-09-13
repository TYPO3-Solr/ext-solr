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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Sorting;

use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\AbstractUriViewHelper;

/**
 * Class SetSortingViewHelper
 */
class SetSortingViewHelper extends AbstractUriViewHelper
{
    /**
     * @inheritDoc
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('sortingName', 'string', 'The sortingName', true);
        $this->registerArgument('sortingDirection', 'string', 'The sortingDirection', true);
    }

    /**
     * Renders URI for setting the sorting.
     */
    public function render()
    {
        $sortingName = $this->arguments['sortingName'];
        $sortingDirection = $this->arguments['sortingDirection'];
        $previousRequest = static::getUsedSearchRequestFromRenderingContext($this->renderingContext);

        return self::getSearchUriBuilder($this->renderingContext)->getSetSortingUri($previousRequest, $sortingName, $sortingDirection);
    }
}
