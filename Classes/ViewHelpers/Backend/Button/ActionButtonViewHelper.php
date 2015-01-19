<?php
namespace ApacheSolrForTypo3\Solr\ViewHelpers\Backend\Button;

/***************************************************************
*  Copyright notice
*
*  (c) 2015 Ingo Renner <ingo@typo3.org>
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

use TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper;


/**
 * Creates a form with a single button to call a backend module action.
 *
 * @author Ingo Renner <ingo@typo3.org>
 * @package ApacheSolrForTypo3\Solr\ViewHelpers\Backend\Button
 */
class ActionButtonViewHelper extends AbstractViewHelper {

	protected $extensionKey = 'solr';
	protected $backendModuleControllerName = 'Administration';

	/**
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
	 * @inject
	 */
	protected $objectManager;


	/**
	 * Renders the ViewHelper
	 *
	 * @param string $action
	 * @param string $label
	 * @return string Markup for form with button
	 */
	public function render($action, $label) {
		$module = $this->renderingContext->getTemplateVariableContainer()->get('module');
		/** @var \ApacheSolrForTypo3\Solr\Backend\SolrModule\AbstractModuleController $module */

		$uriBuilder = $this->controllerContext->getUriBuilder();
		$actionUri = $uriBuilder->reset()->uriFor(
			NULL,
			array(
				'module'       => $module->getName(),
				'moduleAction' => $action
			),
			$this->backendModuleControllerName,
			$this->extensionKey
		);

		$formNode         = $this->getForm($actionUri);
		$submitButtonNode = $this->getButton($label);

		$formNode->addChildNode($submitButtonNode);

		// render form and return output
		return $formNode->evaluate($this->renderingContext);
	}

	/**
	 * Creates a Fluid form node
	 *
	 * @param string $actionUri Action URI
	 * @return \TYPO3\CMS\Fluid\Core\Parser\SyntaxTree\ViewHelperNode
	 */
	protected function getForm($actionUri) {
		$actionUriArgumentNode = $this->objectManager->get(
			'TYPO3\\CMS\\Fluid\\Core\\Parser\\SyntaxTree\\TextNode',
			$actionUri
		);
		$form = $this->objectManager->get('TYPO3\\CMS\\Fluid\\ViewHelpers\\FormViewHelper');
		$form->initialize();
		$form->setRenderingContext($this->renderingContext);
		$formNode = $this->objectManager->get(
			'TYPO3\\CMS\\Fluid\\Core\\Parser\\SyntaxTree\\ViewHelperNode',
			$form,
			array('actionUri' => $actionUriArgumentNode)
		);

		return $formNode;
	}

	/**
	 * Creates a Fluid submit button node
	 *
	 * @param string $label Label
	 * @return \TYPO3\CMS\Fluid\Core\Parser\SyntaxTree\ViewHelperNode
	 */
	protected function getButton($label) {
		$valueArgumentNode = $this->objectManager->get(
			'TYPO3\\CMS\\Fluid\\Core\\Parser\\SyntaxTree\\TextNode',
			$label
		);

		$submitButton = $this->objectManager->get('TYPO3\\CMS\\Fluid\\ViewHelpers\\Form\\SubmitViewHelper');
		$submitButton->initialize();
		$submitButton->setRenderingContext($this->renderingContext);

		$submitButtonNode = $this->objectManager->get(
			'TYPO3\\CMS\\Fluid\\Core\\Parser\\SyntaxTree\\ViewHelperNode',
			$submitButton,
			array('value' => $valueArgumentNode)
		);

		return $submitButtonNode;
	}

}