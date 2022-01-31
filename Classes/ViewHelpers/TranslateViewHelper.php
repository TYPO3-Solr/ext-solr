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

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Fluid\ViewHelpers\TranslateViewHelper as CoreTranslateViewHelper;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;

/**
 * Class TranslateViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class TranslateViewHelper extends CoreTranslateViewHelper
{
    /**
     * @var bool
     */
    protected $escapeChildren = true;

    /**
     * @return string|null
     */
    public function render()
    {
        $result = parent::render();
        $result = self::replaceTranslationPrefixesWithAtWithStringMarker($result);
        $result = vsprintf($result, $this->arguments['arguments']);
        return $result;
    }

    /**
     * Wrapper call to static LocalizationUtility
     *
     * @param string $id Translation Key compatible to TYPO3 Flow
     * @param string $extensionName UpperCamelCased extension key (for example BlogExample)
     * @param array $arguments Arguments to be replaced in the resulting string
     * @param string $languageKey Language key to use for this translation
     * @param string[] $alternativeLanguageKeys Alternative language keys if no translation does exist
     *
     * @return string|null
     */
    public static function translateAndReplaceMarkers($id, $extensionName, $arguments, $languageKey, $alternativeLanguageKeys)
    {
        $result = LocalizationUtility::translate($id, $extensionName, $arguments, $languageKey, $alternativeLanguageKeys);
        $result = self::replaceTranslationPrefixesWithAtWithStringMarker($result);
        $result = vsprintf($result, $arguments);
        return $result;
    }

    /**
     * @param string $argumentsName
     * @param string $closureName
     * @param string $initializationPhpCode
     * @param ViewHelperNode $node
     * @param TemplateCompiler $compiler
     * @return string
     */
    public function compile($argumentsName, $closureName, &$initializationPhpCode, ViewHelperNode $node, TemplateCompiler $compiler)
    {
        return sprintf(
            '\\%1$s::translateAndReplaceMarkers(%2$s[\'key\'] ?? %2$s[\'id\'], %2$s[\'extensionName\'] ?? $renderingContext->getControllerContext()->getRequest()->getControllerExtensionName(), %2$s[\'arguments\'], %2$s[\'languageKey\'], %2$s[\'alternativeLanguageKeys\']) ?? %2$s[\'default\'] ?? %3$s()',
            static::class,
            $argumentsName,
            $closureName
        );
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
