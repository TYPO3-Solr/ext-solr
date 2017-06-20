<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers;

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

use TYPO3\CMS\Fluid\ViewHelpers\TranslateViewHelper as CoreTranslateViewHelper;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class TranslateViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers
 */
class TranslateViewHelper extends CoreTranslateViewHelper
{
    /**
     * @var bool
     */
    protected $escapeChildren = true;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $arguments['extensionName'] = $arguments['extensionName'] === null ? 'Solr' : $arguments['extensionName'];
        $result = parent::renderStatic($arguments, $renderChildrenClosure, $renderingContext);

        $result = self::replaceTranslationPrefixesWithAtWithStringMarker($result);
        if (trim($result) === '') {
            $result = $arguments['default'] !== null ? $arguments['default'] : $renderChildrenClosure();
        }

        $result = vsprintf($result, $arguments['arguments']);
        return $result;
    }

    /**
     * @param $result
     * @return mixed
     */
    protected static function replaceTranslationPrefixesWithAtWithStringMarker($result)
    {
        if (strpos($result, '@') !== false) {
            $result = preg_replace('~\"?@[a-zA-Z]*\"?~', '%s', $result);
        }
        return $result;
    }
}
