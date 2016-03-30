<?php
namespace ApacheSolrForTypo3\Solr\Plugin;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2010-2015 Timo Schmidt <timo.schmidt@aoemedia.de>
 *  (c) 2012-2015 Ingo Renner <ingo@typo3.org>
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

use ApacheSolrForTypo3\Solr\CommandResolver;
use ApacheSolrForTypo3\Solr\Template;
use ApacheSolrForTypo3\Solr\TemplateModifier;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This abstract class should be used to implement commandBased templates.
 * Inheriting plugins should implement the methods getCommandResolver()
 * and getCommandList() the implemented render method applies
 * the registered commands and renders the result into the template.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @author Timo Schmidt <timo.schmidt@aoemedia.de
 * @package TYPO3
 * @subpackage solr
 */
abstract class CommandPluginBase extends PluginBase
{

    /**
     * Should be implemented by an inheriting class to provide a correctly
     * initialized instance of a command resolver.
     *
     * @return CommandResolver
     */
    abstract protected function getCommandResolver();

    /**
     * Should return an array with commands that should be executed.
     *
     * @return array
     */
    abstract protected function getCommandList();

    /**
     * Since TYPO3 8.0 TYPO3\CMS\Core\TimeTracker\TimeTracker is a singleton
     * and no longer available in $GLOBALS['TT']. We 7.6 compatibility
     * we return the $GLOBALS['TT'] otherwise we get an instance of the TimeTracker
     *
     * @return \TYPO3\CMS\Core\TimeTracker\TimeTracker
     */
    protected function getTimeTracker()
    {
        // TYPO3 7.6 and below
        if (isset($GLOBALS['TT'])) {
            return $GLOBALS['TT'];
        } else {
            // Since 8.0
            return GeneralUtility::makeInstance('TYPO3\CMS\Core\TimeTracker\TimeTracker');
        }
    }

    /**
     * This method executes the requested commands and applies the changes to
     * the template.
     *
     * @param $actionResult
     * @throws \UnexpectedValueException
     * @return string Rendered plugin content
     */
    protected function render($actionResult)
    {
        $allCommands = CommandResolver::getAllPluginCommandsList();
        $commandList = $this->getCommandList();

        // render commands matching the plugin's requirements
        foreach ($commandList as $commandName) {
            $this->getTimeTracker()->push('solr-' . $commandName);

            $commandContent = '';
            $commandVariables = $this->executeCommand($commandName);
            if (!is_null($commandVariables)) {
                $commandContent = $this->renderCommand($commandName,
                    $commandVariables);
            }

            $this->template->addSubpart('solr_search_' . $commandName,
                $commandContent);
            unset($subpartTemplate);
            $this->getTimeTracker()->pull($commandContent);
        }

        // remove subparts for commands that are registered but not matching the requirements
        $nonMatchingCommands = array_diff($allCommands, $commandList);
        foreach ($nonMatchingCommands as $nonMatchingCommand) {
            $this->template->addSubpart('solr_search_' . $nonMatchingCommand,
                '');
        }

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr'][$this->getPluginKey()]['renderTemplate'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr'][$this->getPluginKey()]['renderTemplate'] as $classReference) {
                $templateModifier = &GeneralUtility::getUserObj($classReference);

                if ($templateModifier instanceof TemplateModifier) {
                    $templateModifier->modifyTemplate($this->template);
                } else {
                    throw new \UnexpectedValueException(
                        get_class($templateModifier) . ' must implement interface ApacheSolrForTypo3\Solr\TemplateModifier',
                        1310387230
                    );
                }
            }
        }

        $this->javascriptManager->addJavascriptToPage();

        return $this->template->render(Template::CLEAN_TEMPLATE_YES);
    }

    /**
     * Gets the template to be used for rendering a command.
     *
     * @param string $commandName Name of the command to get the template for
     * @return Template The template for the given command
     */
    protected function getCommandTemplate($commandName)
    {
        $subpartTemplate = clone $this->template;
        $subpartTemplate->setWorkingTemplateContent(
            $this->template->getSubpart('solr_search_' . $commandName)
        );

        return $subpartTemplate;
    }

    /**
     * Executes a command.
     *
     * Provides a hook to manipulate a command's template variables.
     *
     * @param string $commandName Name of the command to be executed.
     * @return array Array of template variables returned by the command.
     * @throws \UnexpectedValueException if a command post processor fails to implement interface ApacheSolrForTypo3\Solr\Plugin\CommandPostProcessor
     */
    protected function executeCommand($commandName)
    {
        $commandResolver = $this->getCommandResolver();
        $command = $commandResolver->getCommand($commandName, $this);
        $commandVariables = $command->execute();

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr'][$this->getPluginKey()][$commandName]['postProcessCommandVariables'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr'][$this->getPluginKey()][$commandName]['postProcessCommandVariables'] as $classReference) {
                $commandPostProcessor = GeneralUtility::getUserObj($classReference);

                if ($commandPostProcessor instanceof CommandPostProcessor) {
                    $commandVariables = $commandPostProcessor->postProcessCommandVariables($commandName,
                        $commandVariables);
                } else {
                    throw new \UnexpectedValueException(
                        get_class($commandPostProcessor) . ' must implement interface ApacheSolrForTypo3\Solr\Plugin\CommandPostProcessor',
                        1346079897
                    );
                }
            }
        }

        return $commandVariables;
    }

    /**
     * Renders a command
     *
     * @param string $commandName Name of the command to render
     * @param array $commandVariables Template variables returned by a command
     * @return string The command's variables assigned to the template and rendered
     */
    public function renderCommand($commandName, array $commandVariables)
    {
        $subpartTemplate = $this->getCommandTemplate($commandName);

        foreach ($commandVariables as $variableName => $commandVariable) {
            if (GeneralUtility::isFirstPartOfStr($variableName, 'loop_')) {
                $dividerPosition = strpos($variableName, '|');
                $loopName = substr($variableName, 5, ($dividerPosition - 5));
                $loopedMarkerName = substr($variableName,
                    ($dividerPosition + 1));

                $subpartTemplate->addLoop($loopName, $loopedMarkerName,
                    $commandVariable);
            } elseif (GeneralUtility::isFirstPartOfStr($variableName,
                'subpart_')
            ) {
                $subpartName = substr($variableName, 8);
                $subpartTemplate->addSubpart($subpartName, $commandVariable);
            } elseif (is_array($commandVariable) || is_object($commandVariable)) {
                $subpartTemplate->addVariable($variableName, $commandVariable);
            } else {
                $subpartTemplate->addVariable($commandName, $commandVariables);
            }
        }

        return $subpartTemplate->render();
    }

    /**
     * Retrieves a flexform value from the context plugin.
     *
     * (Wrapper for $this->pi_getFFvalue($this->cObj->data['pi_flexform'],...) )
     *
     * @param string $fieldName
     * @param string $sheet
     * @param string $lang
     * @param string $value
     * @return string|null
     */
    protected function getFlexFormValue($fieldName, $sheet = 'sDEF', $lang = 'lDEF', $value = 'vDEF')
    {
        return $this->pi_getFFvalue($this->cObj->data['pi_flexform'], $fieldName, $sheet, $lang, $value);
    }
}
