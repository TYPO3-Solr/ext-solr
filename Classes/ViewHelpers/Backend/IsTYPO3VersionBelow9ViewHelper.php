<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Backend;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Timo Hund <timo.hund@dkd.de>
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

use ApacheSolrForTypo3\Solr\Util;
use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractConditionViewHelper;

/**
 * Check if the system is below TYPO3 9
 *
 * @todo This ViewHelper and all usages can be dropped when TYPO3 8 support is dropped.
 */
class IsTYPO3VersionBelow9ViewHelper extends AbstractConditionViewHelper
{

    /**
     * @param null $arguments
     * @return bool
     */
    protected static function evaluateCondition($arguments = null)
    {
        return Util::getIsTYPO3VersionBelow9();
    }

    /**
     * Renders <f:then> child if $condition is true, otherwise renders <f:else> child.
     *
     * @todo This copy of the render method is just required for TYPO3 8 backwards compatibility, can be dropped when TYPO3 8 support is dropped.
     *
     * @param bool $condition View helper condition
     * @return string the rendered string
     */
    public function render()
    {
        if (static::evaluateCondition($this->arguments)) {
            return $this->renderThenChild();
        }
        return $this->renderElseChild();
    }
}
