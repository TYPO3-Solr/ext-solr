<?php

namespace ApacheSolrForTypo3\Solr\System\ContentObject;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2017 Timo Hund <timo.hund@dkd.de
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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * StdWrap Service that can be used to apply the ContentObjectRenderer stdWrap functionality on data.
 *
 * @author Timo Hund <timo.hund@dkd.de>
 */
class ContentObjectService
{

    /**
     * @var ContentObjectRenderer
     */
    protected $contentObjectRenderer;

    /**
     * StdWrapService constructor.
     * @param ContentObjectRenderer|null $contentObject
     */
    public function __construct(ContentObjectRenderer $contentObject = null)
    {
        $this->contentObjectRenderer = is_null($contentObject) ? GeneralUtility::makeInstance(ContentObjectRenderer::class) : $contentObject;
    }

    /**
     * This method use $content and $conf and passes it directly to stdWrap.
     *
     * @param string $content
     * @param array $conf
     * @return string
     */
    public function renderSingleContentObject($content = '', $conf = [])
    {
        return $this->contentObjectRenderer->cObjGetSingle($content, $conf);
    }

    /**
     * Very object stdWrap is used with 'field' as $content and 'field.' as $conf with this
     * method you can pass the array and the $key that is used to access $conant and $conf from $array.
     *
     * @param array $array
     * @param string $key
     * @return string
     */
    public function renderSingleContentObjectByArrayAndKey($array = [], $key = '')
    {
        $content = isset($array[$key]) ? $array[$key] : [];
        $conf = isset($array[$key . '.']) ? $array[$key . '.'] : '';
        return $this->renderSingleContentObject($content, $conf);
    }
}
