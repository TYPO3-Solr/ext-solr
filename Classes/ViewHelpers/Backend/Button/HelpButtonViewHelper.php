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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Backend\Button;

use ApacheSolrForTypo3\Solr\ViewHelpers\AbstractSolrViewHelper;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * View helper to return a help button
 *
 */
class HelpButtonViewHelper extends AbstractSolrViewHelper implements ViewHelperInterface
{
    use CompileWithRenderStatic;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     */
    public function initializeArguments()
    {
        $this->registerArgument('title', 'string', 'Title', true);
        $this->registerArgument('description', 'string', 'Description', true);
    }

    /**
     * Render a help button wit the given title and content
     *
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        return BackendUtility::wrapInHelp('', '', '', [
            'title' => $arguments['title'],
            'description' => $arguments['description']
        ]);
    }
}
