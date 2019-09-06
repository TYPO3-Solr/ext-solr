<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Backend;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2017 Rafael Kähm <rafael.kaehm@dkd.de>
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


use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractConditionViewHelper;

/**
 * Condition for checking if type is a string.
 */
class IsStringViewHelper extends AbstractConditionViewHelper
{

    /**
     * Initialize ViewHelper arguments
     *
     * @return void
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('value', 'mixed', 'Value to be verified.', true);
    }

    /**
     * This method decides if the condition is true or false
     *
     * @param array $arguments ViewHelper arguments to evaluate the condition for this ViewHelper.
     * @return bool
     */
    protected static function evaluateCondition($arguments = null)
    {
        return (isset($arguments['value']) && is_string($arguments['value']));
    }
}
