<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010-2011 Timo Schmidt <timo.schmidt@aoemedia.de>
*  (c) 2012 Ingo Renner <ingo@typo3.org>
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
 * This abstract class should be used to implement commandBased templates.
 * Inheriting plugins should implement the methods getCommandResolver()
 * and getCommandList() the implemented render method applys
 * the registered commands and renders the result into the template.
 *
 * @author	Ingo Renner <ingo@typo3.org>
 * @author	Timo Schmidt <timo.schmidt@aoemedia.de
 * @package	TYPO3
 * @subpackage	solr
 */
abstract class tx_solr_pluginbase_CommandPluginBase extends tx_solr_pluginbase_PluginBase{

	/**
	 * Should be implemted by an inheriting class to provide a correctly
	 * initialized intance of a command resolver.
	 *
	 * @return	tx_solr_CommandResolver
	 */
	abstract protected function getCommandResolver();

	/**
	 * Should return an array with commands that should be executed.
	 *
	 * @return	array
	 */
	abstract protected function getCommandList();

	/**
	 * This method executes the requested commands and applies the changes to
	 * the template.
	 *
	 */
	protected function render($actionResult) {
		$commandList     = $this->getCommandList();
		$commandResolver = $this->getCommandResolver();

		foreach ($commandList as $commandName) {
			$GLOBALS['TT']->push('solr-' . $commandName);

			$command          = $commandResolver->getCommand($commandName, $this);
			$commandVariables = $command->execute();

			$subpartTemplate = clone $this->template;
			$subpartTemplate->setWorkingTemplateContent(
				$this->template->getSubpart('solr_search_' . $commandName)
			);

			if (!is_null($commandVariables)) {
				foreach ($commandVariables as $variableName => $commandVariable) {
					if (t3lib_div::isFirstPartOfStr($variableName, 'loop_')) {
						$dividerPosition  = strpos($variableName, '|');
						$loopName         = substr($variableName, 5, ($dividerPosition - 5));
						$loopedMarkerName = substr($variableName, ($dividerPosition + 1));

						$subpartTemplate->addLoop($loopName, $loopedMarkerName, $commandVariable);
					} elseif (t3lib_div::isFirstPartOfStr($variableName, 'subpart_')) {
						$subpartName = substr($variableName, 8);
						$subpartTemplate->addSubpart($subpartName, $commandVariable);
					} elseif (is_array($commandVariable) || is_object($commandVariable)) {
						$subpartTemplate->addVariable($variableName, $commandVariable);
					} else {
						$subpartTemplate->addVariable($commandName, $commandVariables);
					}
				}

				$commandContent = $subpartTemplate->render();
				$this->template->addSubpart('solr_search_' . $commandName, $commandContent);
			}

			unset($subpartTemplate);
			$GLOBALS['TT']->pull($commandContent);
		}

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr'][$this->getPluginKey()]['renderTemplate'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['solr'][$this->getPluginKey()]['renderTemplate'] as $classReference) {
				$templateModifier = &t3lib_div::getUserObj($classReference);

				if ($templateModifier instanceof tx_solr_TemplateModifier) {
					$templateModifier->modifyTemplate($this->template);
				} else {
					throw new UnexpectedValueException(
						get_class($templateModifier) . ' must implement interface tx_solr_TemplateModifier',
						1310387230
					);
				}
			}
		}

		$this->javascriptManager->addJavascriptToPage();

		return $this->template->render(tx_solr_Template::CLEAN_TEMPLATE_YES);
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/pluginbase/class.tx_solr_pluginbase_commandpluginbase.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/solr/classes/pluginbase/class.tx_solr_pluginbase_commandpluginbase.php']);
}

?>