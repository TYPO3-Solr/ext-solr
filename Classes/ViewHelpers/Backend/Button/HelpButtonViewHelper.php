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

use ApacheSolrForTypo3\Solr\ViewHelpers\Backend\AbstractSolrViewHelper;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * View helper to return a help button
 *
 */
class HelpButtonViewHelper extends AbstractSolrViewHelper
{

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Render a help button wit the given title and content
     *
     * @param string $title Help title
     * @return mixed
     */
    public function render($title)
    {
        $content = $this->renderChildren();

        return BackendUtility::wrapInHelp('', '', '', array(
            'title' => $title,
            'description' => $content
        ));
    }
}
