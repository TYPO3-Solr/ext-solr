<?php

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Format;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2015 Ingo Renner <ingo@typo3.org>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3\CMS\Fluid\Core\ViewHelper\Facets\CompilableInterface;

/**
 * ViewHelper to implode an array with a specific glue value.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ImplodeViewHelper extends AbstractSolrViewHelper implements CompilableInterface
{
    // Set $escapeOutput to false in order to ensure that "newline" is handled correctly
    protected $escapeOutput = false;

    /**
     * This ViewHelper can be used to implode the values of an array into one string.
     *
     * @param string $glue The glue value that should be used on implode
     * @param array $value The array that should be imploded.
     * @return string
     */
    public function render($glue = ',', $value = [])
    {
        return static::renderStatic(
            ['glue' => $glue, 'value' => $value],
            $this->buildRenderChildrenClosure(),
            $this->renderingContext
        );
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $glue = isset($arguments['glue']) ? $arguments['glue'] : ',';
        $value = isset($arguments['value']) ? $arguments['value'] : [];

        return implode($glue, $value);
    }
}
