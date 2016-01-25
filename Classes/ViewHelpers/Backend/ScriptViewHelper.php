<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Backend;

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

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\ViewHelpers\Be\AbstractBackendViewHelper;

/**
 * View helper to load a JavaScript file
 *
 * = Examples =
 *
 * <code title="Default">
 * <solr:backend.script file="{f:uri.resource(path:'JavaScripts/chart.js')}" />
 * </code>
 * <output>
 * Loads the given file and adds it to the backend module.
 * </output>
 */
class ScriptViewHelper extends AbstractBackendViewHelper
{

    /**
     * @param string $file JavaScript file to load in the backend module
     */
    public function render($file)
    {
        /** @var PageRenderer $pageRenderer */
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);

        $pageRenderer->addJsFile($file);
    }
}
