<?php
namespace ApacheSolrForTypo3\Solr\Util;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011-2015 Timo Schmidt <timo.schmidt@dkd.de>
 *
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


/**
 * Helper utility class used for string manipulation. Can be injected into your module
 * and mocked in the unit test context.
 *
 * @package ApacheSolrForTypo3\Solr\Util
 */
class StringUtil {

    /**
     * Lowercases a string with the TYPO3 Core functionality.
     *
     * @param string $input
     * @param string $charset
     * @return string
     */
    public function toLower($input, $charset = 'utf-8')
    {
        return $GLOBALS['LANG']->csConvObj->conv_case($charset, $input, 'toLower');
    }
}
