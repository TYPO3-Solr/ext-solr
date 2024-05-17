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

namespace ApacheSolrForTypo3\Solr\System\ContentObject;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * StdWrap Service that can be used to apply the ContentObjectRenderer stdWrap functionality on data.
 */
class ContentObjectService
{
    protected ContentObjectRenderer $contentObjectRenderer;

    /**
     * StdWrapService constructor.
     * @todo: refactor to constructor property
     */
    public function __construct(
        ContentObjectRenderer $contentObjectRenderer = null,
    ) {
        $this->contentObjectRenderer = $contentObjectRenderer ?? GeneralUtility::makeInstance(ContentObjectRenderer::class);
    }

    /**
     * This method use $name and $conf and passes it directly to cObjGetSingle.
     */
    public function renderSingleContentObject(string $name = '', array $conf = []): string
    {
        return $this->contentObjectRenderer->cObjGetSingle($name, $conf);
    }

    /**
     * Very often cObjGetSingle is used with 'field' as $name and 'field.' as $conf with this
     * method you can pass the array and the $key that is used to access $conant and $conf from $array.
     */
    public function renderSingleContentObjectByArrayAndKey(array $array = [], string $key = '')
    {
        $name = $array[$key] ?? [];
        $conf = $array[$key . '.'] ?? '';

        if (!is_array($conf)) {
            return $name;
        }

        return $this->renderSingleContentObject($name, $conf);
    }
}
