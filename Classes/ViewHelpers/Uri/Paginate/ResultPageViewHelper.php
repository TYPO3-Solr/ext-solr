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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Uri\Paginate;

use ApacheSolrForTypo3\Solr\ViewHelpers\Uri\AbstractUriViewHelper;

/**
 * Class ResultPageViewHelper
 */
class ResultPageViewHelper extends AbstractUriViewHelper
{
    /**
     * @inheritdoc
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('page', 'int', 'The page', false, 0);
    }

    /**
     * Renders URI for pagination-page
     */
    public function render()
    {
        $page = $this->arguments['page'];
        $previousRequest = static::getUsedSearchRequestFromRenderingContext($this->renderingContext);
        return self::getSearchUriBuilder($this->renderingContext)->getResultPageUri($previousRequest, $page);
    }
}
