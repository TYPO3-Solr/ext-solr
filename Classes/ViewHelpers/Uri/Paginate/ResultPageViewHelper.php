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
use Closure;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

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
    public static function renderStatic(
        array $arguments,
        Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext,
    ) {
        $page = $arguments['page'];
        $previousRequest = static::getUsedSearchRequestFromRenderingContext($renderingContext);
        return self::getSearchUriBuilder($renderingContext)->getResultPageUri($previousRequest, $page);
    }
}
