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

namespace ApacheSolrForTypo3\Solr\ViewHelpers\Backend\Security;

use InvalidArgumentException;
use RuntimeException;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractConditionViewHelper;

/**
 * This view helper implements an ifHasAccessToModule/else condition for BE users/groups.
 *
 * Class IfHasAccessToModuleViewHelper
 */
class IfHasAccessToModuleViewHelper extends AbstractConditionViewHelper
{
    /**
     * Message for that case, if $arguments['signature'] was used and module does not exist
     */
    public const ERROR_APPENDIX_FOR_WRONG_SIGNATURE_ARGUMENT = 'Please check spelling and style by setting signature="mainName_ExtKeySubmoduleName".';

    /**
     * Message for that case, if $arguments['extension'], $arguments['main'] and $arguments['sub'] are used and module couldn't be resolved
     */
    public const ERROR_APPENDIX_FOR_SIGNATURE_RESOLUTION = 'It was generated by setting extension="%s", main="%s", sub="%s", please check spelling and style by setting this arguments.';

    /**
     * Initializes following arguments: extension, main, sub, signature
     * Renders <f:then> child if the current logged in BE user belongs to the specified role (aka usergroup)
     * otherwise renders <f:else> child.
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('extension', 'string', 'The extension key.');
        $this->registerArgument('main', 'string', 'The main module name.');
        $this->registerArgument('sub', 'string', 'The sub module name.');
        $this->registerArgument('signature', 'string', 'The full signature of module. Simply mainmodulename_submodulename in most cases.');
    }

    /**
     * Evaluate condition
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    protected static function evaluateCondition($arguments = null)
    {
        /* @var BackendUserAuthentication $beUser */
        $beUser = $GLOBALS['BE_USER'];
        try {
            $hasAccessToModule = $beUser->modAccess(
                self::getModuleConfiguration(self::getModuleSignatureFromArguments($arguments))
            );
        } catch (RuntimeException $exception) {
            return false;
        }
        return $hasAccessToModule;
    }

    /**
     * Returns the backend module configuration
     */
    protected static function getModuleConfiguration(string $moduleSignature)
    {
        return $GLOBALS['TBE_MODULES']['_configuration'][$moduleSignature];
    }

    /**
     * Resolves
     */
    protected static function getModuleSignatureFromArguments(array $arguments): string
    {
        $moduleSignature = $arguments['signature'];

        $possibleErrorMessageAppendix = self::ERROR_APPENDIX_FOR_WRONG_SIGNATURE_ARGUMENT;
        $possibleErrorCode = 1496311009;
        if (!is_string($moduleSignature)) {
            $moduleSignature = $arguments['main'];
            $subModuleName = $arguments['extension'] . GeneralUtility::underscoredToUpperCamelCase($arguments['sub']);
            $moduleSignature .= '_' . $subModuleName;
            $possibleErrorMessageAppendix = vsprintf(self::ERROR_APPENDIX_FOR_SIGNATURE_RESOLUTION, [$arguments['extension'], $arguments['main'], $arguments['sub']]);
            $possibleErrorCode = 1496311010;
        }
        if (!isset($GLOBALS['TBE_MODULES']['_configuration'][$moduleSignature])) {
            throw new RuntimeException(vsprintf('Module with signature "%s" is not configured or couldn\'t be resolved. ' . $possibleErrorMessageAppendix, [$moduleSignature]), $possibleErrorCode);
        }
        return $moduleSignature;
    }

    /**
     * Validates arguments given to this view helper.
     *
     * It checks if either signature or extension and main and sub are set.
     */
    public function validateArguments(): void
    {
        parent::validateArguments();

        if (empty($this->arguments['signature'])
            && (
                empty($this->arguments['extension']) || empty($this->arguments['main']) || empty($this->arguments['sub'])
            )
        ) {
            throw new InvalidArgumentException('ifHasAccessToModule view helper requires either "signature" or all three other arguments: "extension", "main" and "sub". Please set arguments properly.', 1496314352);
        }
    }
}
