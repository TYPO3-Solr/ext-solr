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

namespace ApacheSolrForTypo3\Solr\ViewHelpers;

use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\ViewHelperNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Class TranslateViewHelper
 *
 * @author Frans Saris <frans@beech.it>
 * @author Timo Hund <timo.hund@dkd.de>
 */
class TranslateViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;
    /**
     * @var bool
     */
    protected $escapeChildren = true;

    /**
     * Register required keys for translation
     *
     * @see \TYPO3\CMS\Fluid\ViewHelpers\TranslateViewHelper::initializeArguments
     */
    public function initializeArguments(): void
    {
        $this->registerArgument('key', 'string', 'Translation Key');
        $this->registerArgument('id', 'string', 'Translation ID. Same as key.');
        $this->registerArgument('default', 'string', 'If the given locallang key could not be found, this value is used. If this argument is not set, child nodes will be used to render the default');
        $this->registerArgument('arguments', 'array', 'Arguments to be replaced in the resulting string', false, []);
        $this->registerArgument('extensionName', 'string', 'UpperCamelCased extension key (for example BlogExample)');
        $this->registerArgument('languageKey', 'string', 'Language key ("dk" for example) or "default" to use. If empty, use current language. Ignored in non-extbase context.');
        $this->registerArgument('alternativeLanguageKeys', 'array', 'Alternative language keys if no translation does exist. Ignored in non-extbase context.');
    }

    /**
     * Renders the label
     *
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ): string {
        $result = self::translateAndReplaceMarkers(
            (string)($arguments['key'] ?? $arguments['id']),
            (string)($arguments['extensionName'] ?? 'tx_solr'),
            $arguments['arguments'],
            $arguments['languageKey'],
            $arguments['alternativeLanguageKeys'] ?? []
        );

        if ($result === null && isset($arguments['default'])) {
            $result = self::replaceTranslationPrefixesWithAtWithStringMarker(
                (string)($arguments['default'] ?? '')
            );
            if (is_array($arguments['arguments'])) {
                $result = vsprintf($result, $arguments['arguments']);
            }
        }

        return $result ?? '';
    }

    /**
     * Wrapper call to static LocalizationUtility
     *
     * @param string $id Translation Key compatible to TYPO3 Flow
     * @param string $extensionName UpperCamelCased extension key (for example BlogExample)
     * @param array|null $arguments Arguments to be replaced in the resulting string
     * @param string|null $languageKey Language key to use for this translation
     * @param string[]|null $alternativeLanguageKeys Alternative language keys if no translation does exist
     */
    public static function translateAndReplaceMarkers(
        string $id,
        string $extensionName = 'solr',
        ?array $arguments = null,
        ?string $languageKey = null,
        ?array $alternativeLanguageKeys = []
    ): string {
        $result = LocalizationUtility::translate(
            $id,
            $extensionName,
            $arguments,
            $languageKey,
            $alternativeLanguageKeys
        );

        $result = self::replaceTranslationPrefixesWithAtWithStringMarker($result ?? '');
        if (is_array($arguments)) {
            $result = vsprintf($result, $arguments);
        }

        return $result;
    }

    /**
     * @param string $argumentsName
     * @param string $closureName
     * @param string $initializationPhpCode
     * @param ViewHelperNode $node
     * @param TemplateCompiler $compiler
     * @return string
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function compile(
        $argumentsName,
        $closureName,
        &$initializationPhpCode,
        ViewHelperNode $node,
        TemplateCompiler $compiler
    ) {
        return sprintf(
            '\\%1$s::translateAndReplaceMarkers(%2$s[\'key\'] ?? %2$s[\'id\'], %2$s[\'extensionName\'] ?? $renderingContext->getRequest()->getControllerExtensionName(), %2$s[\'arguments\'], %2$s[\'languageKey\'], %2$s[\'alternativeLanguageKeys\']) ?? %2$s[\'default\'] ?? %3$s()',
            static::class,
            $argumentsName,
            $closureName
        );
    }

    protected static function replaceTranslationPrefixesWithAtWithStringMarker(string $result): string
    {
        if (str_contains($result, '@')) {
            $result = (string)preg_replace('~\"?@[a-zA-Z]*\"?~', '%s', $result);
        }

        return $result;
    }
}
