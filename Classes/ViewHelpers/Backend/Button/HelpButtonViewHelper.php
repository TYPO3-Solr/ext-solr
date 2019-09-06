<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Backend\Button;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

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
